<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Dossier;
use App\Enum\StatutDossier;
use App\Entity\AppConfig;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DocumentController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * CONSULTATION CANDIDAT : Voir un fichier envoyé
     * Route totalement isolée de l'administration.
     */
    #[Route('/public-candidat/document/voir/{id}', name: 'app_document_view')]
    public function candidateView(
        Document $document,
        #[Autowire(param: 'documents_directory')] string $documentsDirectory,
        RequestStack $requestStack
    ): Response {
        $dossier = $document->getDossier();
        $sessionDossierId = $requestStack->getSession()->get('dossier_id');

        // Sécurité par session de suivi uniquement
        if (!$sessionDossierId || $sessionDossierId != $dossier->getId()) {
            $this->logger->warning('Tentative d\'accès non autorisé au document', [
                'document_id' => $document->getId(),
                'session_dossier_id' => $sessionDossierId,
                'document_dossier_id' => $dossier->getId(),
            ]);
            return $this->render('security/session_expired.html.twig');
        }

        $filePath = $documentsDirectory . '/' . $document->getCheminFichier();
        
        if (!file_exists($filePath)) {
            $this->logger->error('Fichier introuvable sur le serveur', [
                'document_id' => $document->getId(),
                'chemin_fichier' => $document->getCheminFichier(),
                'file_path' => $filePath,
            ]);
            return new Response("Fichier introuvable.", 404);
        }

        $response = new BinaryFileResponse($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // On définit le type MIME pour forcer l'affichage navigateur
        $mimeType = match($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        return $response;
    }

    /**
     * TÉLÉCHARGEMENT CANDIDAT : Autorisation de stage (Lettre d'Agreement)
     * Génère le PDF et force le téléchargement sans passer par l'espace interne.
     */
    #[Route('/public-candidat/autorisation/telecharger/{id}', name: 'app_candidate_download_authorization')]
    public function downloadAuthorization(
        Dossier $dossier,
        #[Autowire(param: 'kernel.pr
        oject_dir')] string $projectDir,
        RequestStack $requestStack,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): Response {
        $sessionDossierId = $requestStack->getSession()->get('dossier_id');
        
        if (!$sessionDossierId || $sessionDossierId !== $dossier->getId()) {
            $this->logger->warning('Tentative de téléchargement non autorisé', [
                'dossier_id' => $dossier->getId(),
                'session_dossier_id' => $sessionDossierId,
            ]);
            return $this->render('security/session_expired.html.twig');
        }

        if ($dossier->getStatut() !== StatutDossier::VALIDE) {
            $this->logger->warning('Tentative de téléchargement d\'une lettre non valide', [
                'dossier_id' => $dossier->getId(),
                'statut' => $dossier->getStatut()?->value,
            ]);
            return new Response("Votre lettre n'est pas encore prête.", 403);
        }

        // Préparation PDF
        $logoPath = $projectDir . '/public/images/logo_cour_supreme.jpeg';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } else {
            $this->logger->warning('Logo introuvable pour le PDF', [
                'logo_path' => $logoPath,
            ]);
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Times-Roman');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        // Récupération des données spécifiques à ce dossier
        $signatureBase64 = $dossier->getSignatureOfficielle();
        $numeroOfficiel = $dossier->getNumeroOfficiel() ?: '________';

        // ✅ CORRECTION : Récupérer le tampon depuis AppConfig
        $tamponConfig = $entityManager->getRepository(AppConfig::class)
            ->findOneBy(['settingKey' => 'official_stamp']);
        $tamponBase64 = $tamponConfig ? $tamponConfig->getSettingValue() : null;

        // ✅ CORRECTION : Récupérer le nom du signataire depuis AppConfig
        $signataireConfig = $entityManager->getRepository(AppConfig::class)
            ->findOneBy(['settingKey' => 'signataire_nom']);
        $signataireNom = $signataireConfig ? $signataireConfig->getSettingValue() : 'François-Richard David KPENOU';

        // PROTECTION ROBUSTE CONTRE L'ABSENCE DE GD
        if (!function_exists('imagecreatefrompng')) {
            if ($signatureBase64 && str_contains($signatureBase64, 'image/png')) {
                $signatureBase64 = null;
            }
            if (str_contains($logoBase64, 'image/png')) {
                $logoBase64 = '';
            }
        }

        try {
            $html = $this->renderView('emails/internship_authorization.html.twig', [
                'dossier' => $dossier,
                'logo_base64' => $logoBase64,
                'signature_base64' => $signatureBase64,
                'numero_officiel' => $numeroOfficiel,
                'signataire_nom' => $signataireNom,
                'tampon_base64' => $tamponBase64,  // ✅ AJOUT : Passer le tampon au template
            ]);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $this->logger->info('PDF généré avec succès', [
                'dossier_id' => $dossier->getId(),
                'reference' => $dossier->getReference(),
            ]);

            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Autorisation_Stage_CS.pdf"',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du PDF', [
                'dossier_id' => $dossier->getId(),
                'error' => $e->getMessage(),
            ]);
            return new Response("Une erreur est survenue lors de la génération du PDF. Veuillez réessayer.", 500);
        }
    }

    /**
     * ROUTES ADMINISTRATIVES : Réservées au personnel (protégées par firewall)
     */
    #[Route('/admin/documents/voir/{id}', name: 'app_admin_document_view')]
    public function adminView(Document $document, #[Autowire(param: 'documents_directory')] string $documentsDirectory): Response 
    {
        $this->denyAccessUnlessGranted('ROLE_EVALUATEUR');
        
        $filePath = $documentsDirectory . '/' . $document->getCheminFichier();
        
        if (!file_exists($filePath)) {
            $this->logger->error('Fichier admin introuvable', [
                'document_id' => $document->getId(),
                'chemin_fichier' => $document->getCheminFichier(),
            ]);
            throw $this->createNotFoundException('Fichier introuvable.');
        }
        
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        return $response;
    }

    #[Route('/admin/documents/telecharger/{id}', name: 'app_document_download')]
    public function adminDownload(Document $document, #[Autowire(param: 'documents_directory')] string $documentsDirectory): Response 
    {
        $this->denyAccessUnlessGranted('ROLE_EVALUATEUR');
        
        $filePath = $documentsDirectory . '/' . $document->getCheminFichier();
        
        if (!file_exists($filePath)) {
            $this->logger->error('Fichier admin introuvable pour téléchargement', [
                'document_id' => $document->getId(),
                'chemin_fichier' => $document->getCheminFichier(),
            ]);
            throw $this->createNotFoundException('Fichier introuvable.');
        }
        
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getNomOriginal());
        return $response;
    }
}