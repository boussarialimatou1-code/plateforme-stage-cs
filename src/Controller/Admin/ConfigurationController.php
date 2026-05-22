<?php

namespace App\Controller\Admin;

use App\Entity\AppConfig;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configuration')]
#[IsGranted('ROLE_EVALUATEUR')]
class ConfigurationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/lettre-stage', name: 'app_admin_config_internship_letter', methods: ['GET', 'POST'])]
    public function internshipLetter(Request $request, EntityManagerInterface $entityManager): Response
    {
        $configRepo = $entityManager->getRepository(AppConfig::class);
        $signatureConfig = $configRepo->findOneBy(['settingKey' => 'official_signature']);
        $signataireConfig = $configRepo->findOneBy(['settingKey' => 'signataire_nom']);

        if ($request->isMethod('POST')) {
            $signatureData = $request->request->get('signature_data');
            $signataireNom = $request->request->get('signataire_nom');

                        // Sauvegarde tampon
            $tamponData = $request->request->get('tampon_data');
            if ($tamponData) {
                $tamponConfig = $configRepo->findOneBy(['settingKey' => 'official_stamp']);
                try {
                    if (!$tamponConfig) {
                        $tamponConfig = new AppConfig();
                        $tamponConfig->setSettingKey('official_stamp');
                    }
                    $tamponConfig->setSettingValue($tamponData);
                    $entityManager->persist($tamponConfig);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'enregistrement du tampon', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Sauvegarde signature
            if ($signatureData) {
                try {
                    if (!$signatureConfig) {
                        $signatureConfig = new AppConfig();
                        $signatureConfig->setSettingKey('official_signature');
                        $this->logger->info('Création d\'une nouvelle configuration de signature');
                    }
                    
                    $signatureConfig->setSettingValue($signatureData);
                    $entityManager->persist($signatureConfig);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'enregistrement de la signature', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de la signature.');
                }
            }

            // Sauvegarde nom du signataire
            if ($signataireNom) {
                try {
                    if (!$signataireConfig) {
                        $signataireConfig = new AppConfig();
                        $signataireConfig->setSettingKey('signataire_nom');
                    }
                    $signataireConfig->setSettingValue($signataireNom);
                    $entityManager->persist($signataireConfig);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'enregistrement du nom du signataire', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $entityManager->flush();
                $this->logger->info('Configuration enregistrée avec succès');
                $this->addFlash('success', 'La configuration a été enregistrée avec succès.');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la sauvegarde de la configuration', [
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement.');
            }

            if (!$signatureData && !$signataireNom) {
                $this->addFlash('error', 'Aucune donnée reçue.');
            }

            return $this->redirectToRoute('app_admin_config_internship_letter');
        }

        return $this->render('admin/configuration/internship_letter.html.twig', [
            'signature' => $signatureConfig ? $signatureConfig->getSettingValue() : null,
            'signataireNom' => $signataireConfig ? $signataireConfig->getSettingValue() : 'François-Richard David KPENOU',
            'tampon' => $configRepo->findOneBy(['settingKey' => 'official_stamp']) ? $configRepo->findOneBy(['settingKey' => 'official_stamp'])->getSettingValue() : null,
        ]);
    }
}