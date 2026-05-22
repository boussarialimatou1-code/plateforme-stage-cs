<?php

namespace App\Controller;

use App\Entity\Candidat;
use App\Entity\Dossier;
use App\Enum\StatutDossier;
use App\Form\CandidatureType;
use App\Service\DossierManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/postuler', name: 'app_postuler', methods: ['GET', 'POST'])]
    public function postuler(Request $request, DossierManager $dossierManager, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $lastSubmission = $session->get('last_submission_time', 0);
        if (time() - $lastSubmission < 30) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'errors' => ['global' => "Veuillez patienter quelques instants avant de soumettre un nouveau dossier."]]);
            }
        }

        $candidat = new Candidat();
        $dossier = new Dossier();

        $form = $this->createForm(CandidatureType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->get('fax_number')->getData()) {
                return $this->json(['success' => true, 'redirect' => $this->generateUrl('app_home')]);
            }

            if ($form->isValid()) {
                $session->set('last_submission_time', time());
                $data = $form->getData();

                $candidat->setEmail($data['email'])
                    ->setNom($data['nom'])
                    ->setPrenom($data['prenom'])
                    ->setTelephone($data['telephone'])
                    ->setSexe($data['sexe'])
                    ->setDob($data['dob'] instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($data['dob']) : null)
                    ->setEtablissement($data['etablissement'])
                    ->setNiveau($data['niveau'])
                    ->setFiliere($data['filiere']);

                $existingCandidat = $entityManager->getRepository(Candidat::class)->findOneBy(['email' => $candidat->getEmail()]);
                if ($existingCandidat) {
                    $errorMsg = "Cet email est deja associe a une candidature.";
                    if ($request->isXmlHttpRequest()) {
                        return $this->json(['success' => false, 'errors' => ['email_first' => $errorMsg]]);
                    }
                    $this->addFlash('error', $errorMsg);
                    return $this->render('home/postuler.html.twig', ['form' => $form->createView()]);
                }

                $dossier->setTypeStage($data['type_stage'])
                    ->setDomaine($data['domaine'])
                    ->setDureeMois((int)$data['duree']);

                $files = [
                    'cv' => $form->get('cv')->getData(),
                    'lm' => $form->get('lm')->getData(),
                    'id_card' => $form->get('id_card')->getData(),
                    'photo' => $form->get('photo')->getData(),
                    'recommandation' => $form->get('recommandation')->getData(),
                ];

                try {
                    $dossierManager->createDossier($candidat, $dossier, $files);

                    $message = "Candidature enregistree. Votre code de suivi vous a ete envoye par email.";

                    if ($request->isXmlHttpRequest()) {
                        $this->addFlash('success', $message);
                        return $this->json(['success' => true, 'redirect' => $this->generateUrl('app_verification')]);
                    }
                    $this->addFlash('success', $message);
                    return $this->redirectToRoute('app_verification');
                } catch (\Exception $e) {
                    $this->logger->error('Erreur creation dossier candidat', [
                        'email' => $candidat->getEmail(),
                        'error' => $e->getMessage(),
                    ]);
                    if ($request->isXmlHttpRequest()) {
                        return $this->json(['success' => false, 'errors' => ['global' => $e->getMessage()]]);
                    }
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                if ($request->isXmlHttpRequest()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $origin = $error->getOrigin();
                        $name = $origin->getName();

                        if ($origin->isRoot()) {
                            $name = 'global';
                        } elseif ($origin->getParent() && $origin->getParent()->getName() === 'email') {
                            $name = 'email_' . $name;
                        }

                        if (!isset($errors[$name])) {
                            $errors[$name] = [];
                        }
                        $errors[$name][] = $error->getMessage();
                    }

                    $formattedErrors = [];
                    foreach ($errors as $field => $messages) {
                        $formattedErrors[$field] = count($messages) === 1 ? $messages[0] : implode('<br>', $messages);
                    }

                    return $this->json(['success' => false, 'errors' => $formattedErrors]);
                }
            }
        }

        return $this->render('home/postuler.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/modifier-candidature', name: 'app_modifier_candidature', methods: ['GET', 'POST'])]
    public function modifier(Request $request, DossierManager $dossierManager, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $code = $session->get('candidate_code');

        if (!$code) {
            $this->addFlash('error', 'Veuillez d\'abord vous identifier avec votre code de suivi.');
            return $this->redirectToRoute('app_verification');
        }

        $candidat = $entityManager->getRepository(Candidat::class)->findOneBy(['codeAcces' => $code]);
        if (!$candidat) {
            $this->addFlash('error', 'Candidat introuvable. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_verification');
        }

        $dossier = $entityManager->getRepository(Dossier::class)->findOneBy(['candidat' => $candidat], ['dateCreation' => 'DESC']);
        if (!$dossier) {
            $this->addFlash('error', "Aucun dossier trouve.");
            return $this->redirectToRoute('app_home');
        }

        if ($dossier->getStatut() !== StatutDossier::EN_ATTENTE) {
            $this->addFlash('error', "Modification impossible : votre dossier est deja en cours de traitement.");
            return $this->redirectToRoute('app_suivi');
        }

        $formData = [
            'nom' => $candidat->getNom(),
            'prenom' => $candidat->getPrenom(),
            'email' => $candidat->getEmail(),
            'telephone' => $candidat->getTelephone(),
            'sexe' => $candidat->getSexe(),
            'dob' => $candidat->getDob(),
            'etablissement' => $candidat->getEtablissement(),
            'niveau' => $candidat->getNiveau(),
            'filiere' => $candidat->getFiliere(),
            'type_stage' => $dossier->getTypeStage(),
            'domaine' => $dossier->getDomaine(),
            'duree' => $dossier->getDureeMois(),
        ];

        $form = $this->createForm(CandidatureType::class, $formData, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $candidat->setNom($data['nom'])
                    ->setPrenom($data['prenom'])
                    ->setTelephone($data['telephone'])
                    ->setSexe($data['sexe'])
                    ->setDob($data['dob'] instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($data['dob']) : null)
                    ->setEtablissement($data['etablissement'])
                    ->setNiveau($data['niveau'])
                    ->setFiliere($data['filiere']);

                $entityManager->persist($candidat);
                $entityManager->flush();

                $dossier->setTypeStage($data['type_stage'])
                    ->setDomaine($data['domaine'])
                    ->setDureeMois((int)$data['duree']);

                $files = [
                    'cv' => $form->get('cv')->getData(),
                    'lm' => $form->get('lm')->getData(),
                    'id_card' => $form->get('id_card')->getData(),
                    'photo' => $form->get('photo')->getData(),
                    'recommandation' => $form->get('recommandation')->getData(),
                ];

                $dossierManager->updateDossier($dossier, $files);
                $entityManager->flush();

                $this->logger->info('Candidature modifiee avec succes', [
                    'dossier_ref' => $dossier->getReference(),
                    'candidat_id' => $candidat->getId(),
                ]);

                $this->addFlash('success', 'Votre candidature a ete mise a jour avec succes.');
                return $this->redirectToRoute('app_suivi');
            } catch (\Exception $e) {
                $this->logger->error('Erreur modification dossier candidat', [
                    'dossier_ref' => $dossier->getReference(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', "Erreur lors de la mise a jour : " . $e->getMessage());
            }
        }

        return $this->render('home/modifier.html.twig', [
            'form' => $form->createView(),
            'dossier' => $dossier,
        ]);
    }

    #[Route('/verification', name: 'app_verification')]
    public function verification(): Response
    {
        return $this->render('home/verification.html.twig');
    }

    #[Route('/suivi', name: 'app_suivi', methods: ['GET', 'POST'])]
    public function suivi(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();

        $attempts = $session->get('login_attempts', 0);
        $lastAttempt = $session->get('last_attempt_time', 0);

        if ($attempts >= 5 && (time() - $lastAttempt) < 60) {
            $this->addFlash('error', "Trop de tentatives. Veuillez patienter 1 minute.");
            return $this->redirectToRoute('app_verification');
        }

        $code = $request->request->get('code') ?? $session->get('candidate_code');

        if (!$code) return $this->redirectToRoute('app_verification');

        $candidat = $entityManager->getRepository(Candidat::class)->findOneBy(['codeAcces' => $code]);

        if (!$candidat) {
            $session->set('login_attempts', $attempts + 1);
            $session->set('last_attempt_time', time());

            $session->remove('candidate_code');
            $this->addFlash('error', "Code d'acces invalide.");
            return $this->redirectToRoute('app_verification');
        }

        $session->set('login_attempts', 0);
        $session->set('candidate_code', $code);
        $dossier = $entityManager->getRepository(Dossier::class)->findOneBy(['candidat' => $candidat], ['dateCreation' => 'DESC']);

        if (!$dossier) {
            $this->addFlash('error', "Aucun dossier trouve.");
            return $this->redirectToRoute('app_home');
        }

        $session->set('dossier_id', $dossier->getId());

        return $this->render('home/suivi.html.twig', ['dossier' => $dossier]);
    }

    #[Route('/quitter-suivi', name: 'app_logout_candidate')]
    public function logoutCandidate(Request $request): Response
    {
        $request->getSession()->invalidate();
        return $this->redirectToRoute('app_home');
    }


    #[Route('/renouveler-stage', name: 'app_renouveler', methods: ['GET', 'POST'])]
    public function renouveler(Request $request, DossierManager $dossierManager, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $code = $session->get('candidate_code');

        if (!$code) {
            $this->addFlash('error', 'Veuillez d\'abord vous identifier avec votre code de suivi.');
            return $this->redirectToRoute('app_verification');
        }

        $candidat = $entityManager->getRepository(Candidat::class)->findOneBy(['codeAcces' => $code]);
        if (!$candidat) {
            $this->addFlash('error', 'Candidat introuvable.');
            return $this->redirectToRoute('app_verification');
        }

        $dossier = $entityManager->getRepository(Dossier::class)->findOneBy(['candidat' => $candidat], ['dateCreation' => 'DESC']);
        if (!$dossier || $dossier->getStatut() !== StatutDossier::VALIDE) {
            $this->addFlash('error', "Vous ne pouvez demander un renouvellement que pour un stage validé.");
            return $this->redirectToRoute('app_suivi');
        }

        // Vérification de la période de renouvellement (15 jours avant la fin)
        $joursRestants = $dossier->getJoursRestants();
        if ($joursRestants === null || $joursRestants > 15) {
            $message = "Vous ne pouvez demander un renouvellement que durant les 15 derniers jours de votre stage.";
            if ($dossier->getDateFinStage()) {
                $dateOuverture = $dossier->getDateFinStage()->modify('-15 days');
                $message .= " Vous pourrez soumettre votre demande à partir du " . $dateOuverture->format('d/m/Y') . ".";
            }
            $this->addFlash('info', $message);
            return $this->redirectToRoute('app_suivi');
        }

        $form = $this->createForm(\App\Form\RenouvellementType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $dossierManager->createRenouvellement(
                    $dossier,
                    (int)$form->get('duree')->getData(),
                    $form->get('lettre_renouvellement')->getData()
                );

                $this->addFlash('success', 'Votre demande de renouvellement a été enregistrée avec succès.');
                return $this->redirectToRoute('app_suivi');
            } catch (\Exception $e) {
                $this->addFlash('error', "Erreur lors de la demande : " . $e->getMessage());
            }
        }

        return $this->render('home/renouveler.html.twig', [
            'form' => $form->createView(),
            'dossier' => $dossier,
        ]);
    }

    #[Route('/recuperer-code', name: 'app_recover_code', methods: ['GET', 'POST'])]
    public function recoverCode(Request $request, EntityManagerInterface $entityManager, \App\Service\NotificationService $notificationService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recover_code', $request->request->get('_token'))) {
                return $this->redirectToRoute('app_recover_code');
            }

            $candidat = $entityManager->getRepository(Candidat::class)->findOneBy(['email' => $request->request->get('email')]);

            if ($candidat && !$candidat->getDossiers()->isEmpty()) {
                try {
                    $newCode = (string) random_int(100000, 999999);
                    $candidat->setCodeAcces($newCode);
                    $entityManager->flush();

                    $notificationService->sendAccessCode($candidat);
                    $this->addFlash('success', "Un nouveau code de suivi a ete genere et envoye a l'adresse " . $candidat->getEmail());
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de la recuperation de code candidat', [
                        'candidat_email' => $candidat->getEmail(),
                        'error' => $e->getMessage(),
                    ]);
                    $this->addFlash('error', "Erreur lors de l'envoi de l'email. Veuillez reessayer plus tard.");
                }
            } else {
                $this->addFlash('error', "Aucun compte trouve pour cet email.");
                return $this->redirectToRoute('app_recover_code');
            }

            return $this->redirectToRoute('app_verification');
        }

        return $this->render('home/recover_code.html.twig');
    }
}