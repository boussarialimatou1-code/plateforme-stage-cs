<?php

namespace App\Controller\Admin;

use App\Entity\AppConfig;
use App\Entity\Dossier;
use App\Entity\Evaluation;
use App\Enum\StatutDossier;
use App\Enum\TypeStructure;
use App\Repository\DossierRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'évaluation des dossiers.
 */
#[Route('/admin/evaluations')]
class EvaluatorController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
        private LoggerInterface $logger,
    ) {}

    private function getSignataireNom(EntityManagerInterface $entityManager): string
    {
        $config = $entityManager->getRepository(AppConfig::class)->findOneBy(['settingKey' => 'signataire_nom']);
        return $config ? $config->getSettingValue() : 'François-Richard David KPENOU';
    }

    private function getTamponOfficiel(EntityManagerInterface $entityManager): ?string
    {
        $config = $entityManager->getRepository(AppConfig::class)->findOneBy(['settingKey' => 'official_stamp']);
        return $config ? $config->getSettingValue() : null;
    }

    #[Route('/', name: 'app_evaluator_evaluations_list')]
    public function list(DossierRepository $dossierRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_EVALUATEUR')) {
            throw $this->createAccessDeniedException('Accès réservé aux évaluateurs uniquement.');
        }

        $statutFilter = $request->query->get('statut');
        $structureFilter = $request->query->get('structure');

        $criteria = [];
        if ($statutFilter) {
            $enumStatut = StatutDossier::tryFromLabel($statutFilter);
            if ($enumStatut) {
                $criteria['statut'] = $enumStatut;
            }
        }

        /** @var \App\Entity\Evaluateur $user */
        $user = $this->getUser();

        if (!$user->isMainEvaluator()) {
            $criteria['evaluateur'] = [$user, null]; // permet de recuperer les dossiers non traités ou ceux traités appartenant à cet évaluateur
        }

        if ($structureFilter) {
            $enumStatut = TypeStructure::tryFrom($structureFilter);
            if ($enumStatut) {
                $criteria['structure'] = $enumStatut;
            }
        }

        $dossiers = $dossierRepository->findBy($criteria);
        $evaluators = [];
        if ($user->isMainEvaluator() || $this->isGranted('ROLE_ADMIN')) {
            $evaluators = $entityManager->getRepository(\App\Entity\Evaluateur::class)->findAll();
        }

        // dd($dossiers, $dossiers2, $criteria);
        return $this->render('admin/evaluations/list.html.twig', [
            'dossiers' => $dossiers,
            'evaluators' => $evaluators,
        ]);
    }


    #[Route('/{id}', name: 'app_evaluator_evaluations_show')]
    public function show(Dossier $dossier): Response
    {
        /** @var \App\Entity\Evaluateur $user */
        $user = $this->getUser();

        if (!$user->isMainEvaluator() && $dossier->getEvaluateur() !== null && $dossier->getEvaluateur() !== $user) {
            throw $this->createAccessDeniedException('Ce dossier ne vous est pas assigné.');
        }

        // ✅ MODIFIÉ : On peut évaluer si le statut n'est ni VALIDE ni REJETE
        $canEvaluate = ! \in_array($dossier->getStatut(), [StatutDossier::VALIDE, StatutDossier::REJETE]);

        $evaluation = new Evaluation();

        $form = $this->createForm(\App\Form\EvaluationType::class, $evaluation, [
            'action' => $this->generateUrl('app_evaluator_evaluations_submit', ['id' => $dossier->getId()]),
            'method' => 'POST',
            'can_reserve' => $dossier->getStatut() !== StatutDossier::MIS_EN_RESERVE,
        ]);

        return $this->render('admin/evaluations/show.html.twig', [
            'dossier' => $dossier,
            'form' => $form->createView(),
            'canEvaluate' => $canEvaluate,
        ]);
    }

    private function calculerDateFin(\DateTimeImmutable $dateDebut, int $dureeMois): \DateTimeImmutable
    {
        $dateFin = $dateDebut->modify('+' . ($dureeMois * 30) . ' days');

        $jourSemaine = (int) $dateFin->format('N');

        if ($jourSemaine === 6) {
            $dateFin = $dateFin->modify('-1 day');
        } elseif ($jourSemaine === 7) {
            $dateFin = $dateFin->modify('-2 days');
        }

        return $dateFin;
    }

    #[Route('/{id}/noter', name: 'app_evaluator_evaluations_submit', methods: ['POST'])]
    public function submit(Dossier $dossier, Request $request, EntityManagerInterface $entityManager): Response
    {
        $evaluation = new Evaluation();

        $form = $this->createForm(\App\Form\EvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evaluation->setDossier($dossier);
            $evaluation->setEvaluateur($this->getUser());
            $dossier->setEvaluateur($this->getUser());
            $avis = $evaluation->getAvis();

            if ($avis === \App\Enum\EvaluationAvis::FAVORABLE) {
                $dateDebut = $form->get('dateDebutStage')->getData();
                $structure = $form->get('structure')->getData();


                if (!$dateDebut) {
                    $this->addFlash('error', 'La date de début du stage est obligatoire pour un avis favorable.');
                    return $this->redirectToRoute('app_evaluator_evaluations_show', ['id' => $dossier->getId()]);
                }

                // ✅ CHANGEMENT : Statut = ACCEPTE (pas VALIDE tout de suite)
                $dossier->setStatut(StatutDossier::ACCEPTE);

                $duree = $dossier->getDureeMois() ?: 3;

                $dateDebutImmutable = \DateTimeImmutable::createFromInterface($dateDebut);
                $dossier->setDateDebutStage($dateDebutImmutable);
                $dossier->setStructure($structure);

                $dateFinImmutable = $this->calculerDateFin($dateDebutImmutable, $duree);
                $dossier->setDateFinStage($dateFinImmutable);

                $entityManager->persist($evaluation);
                $entityManager->flush();

                // ✅ IMPORTANT : PAS de notification au candidat maintenant !
                // La notification sera envoyée quand la lettre sera finalisée

                $this->addFlash('success', 'Candidat admis ! Veuillez maintenant générer et signer la lettre officielle.');

                return $this->redirectToRoute('app_evaluator_dossier_prepare_letter', ['id' => $dossier->getId()]);
            } elseif ($avis === \App\Enum\EvaluationAvis::DEFAVORABLE) {
                $dossier->setStatut(StatutDossier::REJETE);

                $entityManager->persist($evaluation);
                $entityManager->flush();

                try {
                    $this->notificationService->sendStatusUpdate($dossier);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur envoi notification refus', [
                        'dossier_id' => $dossier->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->addFlash('warning', 'Évaluation enregistrée : Candidature REFUSÉE. Le candidat a été notifié.');
            } else {
                $dossier->setStatut(StatutDossier::MIS_EN_RESERVE);

                $entityManager->persist($evaluation);
                $entityManager->flush();

                try {
                    $this->notificationService->sendStatusUpdate($dossier);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur envoi notification mise en réserve', [
                        'dossier_id' => $dossier->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->addFlash('info', 'Évaluation enregistrée : Dossier MIS EN ATTENTE. Le candidat a été notifié.');
            }

            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $this->addFlash('error', 'Le formulaire d\'évaluation est invalide.');
        return $this->redirectToRoute('app_evaluator_evaluations_show', ['id' => $dossier->getId()]);
    }

    #[Route('/{id}/assigner', name: 'app_evaluator_evaluations_assign', methods: ['POST'])]
    public function assign(Dossier $dossier, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Evaluateur $user */
        $user = $this->getUser();

        if (!$user->isMainEvaluator() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', "Vous n'avez pas le droit de répartir les dossiers.");
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $evaluateurId = $request->request->get('evaluateur_id');
        if ($evaluateurId) {
            $evaluateur = $entityManager->getRepository(\App\Entity\Evaluateur::class)->find($evaluateurId);
            if ($evaluateur) {
                $dossier->setEvaluateur($evaluateur);
                $entityManager->flush();

                try {
                    $this->notificationService->sendAssignmentNotification($dossier, $evaluateur);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'envoi de la notification d\'assignation', [
                        'dossier_id' => $dossier->getId(),
                        'evaluateur_id' => $evaluateur->getId(),
                        'evaluateur_email' => $evaluateur->getEmail(),
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->addFlash('success', 'Le dossier a été assigné à ' . $evaluateur->getNom() . ' ' . $evaluateur->getPrenom());
            }
        } else {
            $dossier->setEvaluateur(null);
            $entityManager->flush();
            $this->addFlash('info', 'Le dossier a été désassigné.');
        }

        return $this->redirectToRoute('app_evaluator_evaluations_list');
    }

    #[Route('/assigner-lot', name: 'app_evaluator_evaluations_batch_assign', methods: ['POST'])]
    public function batchAssign(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Evaluateur $user */
        $user = $this->getUser();

        if (!$user->isMainEvaluator() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', "Vous n'avez pas le droit de répartir les dossiers.");
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $evaluateurId = $request->request->get('evaluateur_id');
        $dossierIds = explode(',', $request->request->get('dossier_ids', ''));

        if (!$evaluateurId || empty($dossierIds)) {
            $this->addFlash('error', 'Données invalides.');
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $evaluateur = $entityManager->getRepository(\App\Entity\Evaluateur::class)->find($evaluateurId);
        if (!$evaluateur) {
            $this->addFlash('error', 'Évaluateur non trouvé.');
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $count = 0;
        $notificationErrors = 0;

        foreach ($dossierIds as $dossierId) {
            $dossier = $entityManager->getRepository(Dossier::class)->find((int) $dossierId);
            // ✅ MODIFIÉ : On peut assigner même les dossiers ACCEPTE (pour la lettre)
            if ($dossier && ! \in_array($dossier->getStatut(), [StatutDossier::VALIDE, StatutDossier::REJETE])) {
                $dossier->setEvaluateur($evaluateur);
                $count++;

                try {
                    $this->notificationService->sendAssignmentNotification($dossier, $evaluateur);
                } catch (\Exception $e) {
                    $notificationErrors++;
                    $this->logger->error('Erreur lors de l\'envoi de la notification d\'assignation en lot', [
                        'dossier_id' => $dossier->getId(),
                        'evaluateur_id' => $evaluateur->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $entityManager->flush();

        $message = $count . ' dossier(s) assigné(s) à ' . $evaluateur->getNom() . ' ' . $evaluateur->getPrenom() . '.';
        if ($notificationErrors > 0) {
            $message .= ' (' . $notificationErrors . ' notification(s) d\'email non envoyée(s)).';
        }

        $this->addFlash('success', $message);
        return $this->redirectToRoute('app_evaluator_evaluations_list');
    }

    /**
     * Prépare la lettre officielle pour un dossier accepté
     */
    #[Route('/lettre/{id}', name: 'app_evaluator_dossier_prepare_letter')]
    public function prepareLetter(Dossier $dossier, EntityManagerInterface $entityManager): Response
    {
        // ✅ MODIFIÉ : Le dossier doit être ACCEPTE ou VALIDE
        if (! \in_array($dossier->getStatut(), [StatutDossier::ACCEPTE, StatutDossier::VALIDE])) {
            $this->addFlash('error', "Ce dossier n'est pas prêt pour la génération de la lettre.");
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $tamponConfig = $entityManager->getRepository(AppConfig::class)->findOneBy(['settingKey' => 'official_stamp']);

        return $this->render('admin/dossier/prepare_letter.html.twig', [
            'dossier' => $dossier,
            'signataire_nom' => $this->getSignataireNom($entityManager),
            'tampon' => $tamponConfig ? $tamponConfig->getSettingValue() : null,
        ]);
    }
    /**
     * Prévisualise la lettre en PDF avant signature
     */
    #[Route('/lettre/apercu/{id}', name: 'app_evaluator_dossier_preview_letter')]
    public function previewLetter(
        Dossier $dossier,
        EntityManagerInterface $entityManager
    ): Response {
        // ✅ MODIFIÉ : Le dossier doit être ACCEPTE ou VALIDE
        if (! \in_array($dossier->getStatut(), [StatutDossier::ACCEPTE, StatutDossier::VALIDE])) {
            $this->addFlash('error', "Ce dossier n'est pas prêt pour la génération de la lettre.");
            return $this->redirectToRoute('app_evaluator_evaluations_list');
        }

        $projectDir = $this->getParameter('kernel.project_dir');

        $logoPath = $projectDir . '/public/images/logo_cour_supreme.jpeg';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $signatureBase64 = $dossier->getSignatureOfficielle();
        $numeroOfficiel = $dossier->getNumeroOfficiel() ?: '________';

        $tamponBase64 = $this->getTamponOfficiel($entityManager);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Times-Roman');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('emails/internship_authorization.html.twig', [
            'dossier' => $dossier,
            'logo_base64' => $logoBase64,
            'signature_base64' => $signatureBase64,
            'numero_officiel' => $numeroOfficiel,
            'signataire_nom' => $this->getSignataireNom($entityManager),
            'tampon_base64' => $tamponBase64,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Apercu_Lettre_' . $dossier->getReference() . '.pdf"',
        ]);
    }

    /**
     * Sauvegarde la lettre officielle (numéro + signature + tampon)
     * ✅ C'EST ICI que le statut passe à VALIDE et que le candidat est notifié
     */
    #[Route('/lettre/sauvegarder/{id}', name: 'app_evaluator_dossier_save_letter', methods: ['POST'])]
    public function saveLetter(
        Request $request,
        Dossier $dossier,
        EntityManagerInterface $entityManager
    ): Response {
        $numero = $request->request->get('numero_officiel');
        $signature = $request->request->get('signature_data');
        $tampon = $request->request->get('tampon_data');

        if ($numero) {
            $dossier->setNumeroOfficiel($numero);
        }

        if ($signature) {
            $dossier->setSignatureOfficielle($signature);
        }

        if ($tampon) {
            $tamponConfig = $entityManager->getRepository(AppConfig::class)
                ->findOneBy(['settingKey' => 'official_stamp']);
            if (!$tamponConfig) {
                $tamponConfig = new AppConfig();
                $tamponConfig->setSettingKey('official_stamp');
            }
            $tamponConfig->setSettingValue($tampon);
            $entityManager->persist($tamponConfig);
        }

        // ✅ MOMENT CLÉ : La lettre est finalisée → le statut passe à VALIDE
        $dossier->setStatut(StatutDossier::VALIDE);
        $dossier->setLettreFinalisee(true);

        $entityManager->flush();

        // ✅ NOTIFICATION AU CANDIDAT UNIQUEMENT MAINTENANT
        try {
            $this->notificationService->sendStatusUpdate($dossier);
            $this->logger->info('Notification envoyée au candidat après finalisation de la lettre', [
                'dossier_id' => $dossier->getId(),
                'dossier_ref' => $dossier->getReference(),
                'candidat_email' => $dossier->getCandidat()?->getEmail(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification après signature', [
                'dossier_id' => $dossier->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->addFlash('success', '✅ La lettre officielle a été signée et enregistrée. Le candidat a été notifié par email.');
        return $this->redirectToRoute('app_evaluator_evaluations_list');
    }
}
