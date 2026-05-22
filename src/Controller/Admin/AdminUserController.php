<?php

/**
 * ================================================================================================
 * GESTION DES UTILISATEURS ADMIN (AdminUserController)
 * ================================================================================================
 * 
 * FICHIER : src/Controller/Admin/AdminUserController.php
 * 
 * ROLE PRINCIPAL :
 * Ce contrôleur permet à l'administrateur de gérer les comptes des autres administrateurs
 * et évaluateurs. C'est le seul endroit où on peut créer/supprimer des comptes internes.
 * 
 * FONCTIONNALITÉS :
 * - Lister tous les agents (admins + évaluateurs)
 * - Créer un nouveau compte agent
 * - Supprimer un compte agent
 * 
 * NE S'OCCUPE PAS DES CANDIDATS ! Les candidats sont créés automatiquement quand ils
 * s'inscrivent sur la plateforme publique.
 * 
 * --------------------------------------------------------------------------------
 * ROUTES GÉRÉES :
 * --------------------------------------------------------------------------------
 * 
 * GET  /admin/gestion/utilisateurs/        → app_admin_users_list   → Liste des agents
 * GET  /admin/gestion/utilisateurs/nouveau → app_admin_users_new    → Formulaire création
 * POST /admin/gestion/utilisateurs/nouveau → app_admin_users_new    → Créer un agent
 * POST /admin/gestion/utilisateurs/supprimer/{id} → app_admin_users_delete → Supprimer
 * 
 * --------------------------------------------------------------------------------
 * SÉCURITÉ :
 * --------------------------------------------------------------------------------
 * 
 * #[IsGranted('ROLE_ADMIN')] → Réservé aux admins uniquement
 * Un simple évaluateur ne peut PAS gérer les utilisateurs
 * 
 * --------------------------------------------------------------------------------
 * DEPENDANCES :
 * --------------------------------------------------------------------------------
 */

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NotificationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contrôleur de gestion des utilisateurs admins/évaluateurs.
 */
#[Route('/admin/gestion/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.super_admin_email')]
        private string $superAdminEmail,
    ) {
    }

    /**
     * Vérifie si l'utilisateur connecté est l'admin principal
     */
    private function isMainAdmin(): bool
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        return $currentUser && $currentUser->getEmail() === $this->superAdminEmail;
    }

    /**
     * Vérifie si l'utilisateur cible est l'admin principal (protégé)
     */
    private function isTargetMainAdmin(Utilisateur $user): bool
    {
        return $user->getEmail() === $this->superAdminEmail;
    }

    #[Route('/', name: 'app_admin_users_list')]
    public function list(UtilisateurRepository $utilisateurRepository): Response
    {
        $users = $utilisateurRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role_eval OR u.roles LIKE :role_admin')
            ->setParameter('role_eval', '%ROLE_EVALUATEUR%')
            ->setParameter('role_admin', '%ROLE_ADMIN%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'super_admin_email' => $this->superAdminEmail,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        $isMainAdmin = $this->isMainAdmin();
        
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        
        $form = $this->createForm(\App\Form\UserAdminType::class, null, [
            'data_class' => null,
            'is_main_admin' => $isMainAdmin,
            'is_authorized_to_manage_admins' => false
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Nettoyage et validation stricte des rôles (anti-injection)
            $roles = $data['roles'] ?? [];
            $allowedRoles = ['ROLE_EVALUATEUR'];
            if ($isMainAdmin) {
                $allowedRoles[] = 'ROLE_ADMIN';
            }
            $roles = array_values(array_intersect($roles, $allowedRoles));
            if (empty($roles)) {
                $roles = ['ROLE_EVALUATEUR'];
            }

            // Seul l'Admin Principal peut créer un autre Admin
            if (in_array('ROLE_ADMIN', $roles) && !$isMainAdmin) {
                $this->addFlash('error', "Vous n'avez pas l'autorisation de créer un compte Administrateur.");
                return $this->redirectToRoute('app_admin_users_list');
            }

            // Vérification de l'unicité de l'email AVANT la création
            $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                $this->addFlash('error', sprintf(
                    "L'adresse email \"%s\" est déjà utilisée par un autre compte (%s %s).",
                    $data['email'],
                    $existingUser->getPrenom(),
                    $existingUser->getNom()
                ));
                return $this->render('admin/users/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Vérification de l'unicité du téléphone AVANT la création
            $existingPhone = $entityManager->getRepository(Utilisateur::class)->findOneBy(['telephone' => $data['telephone']]);
            if ($existingPhone) {
                $this->addFlash('error', sprintf(
                    "Le numéro de téléphone \"%s\" est déjà utilisé par un autre compte (%s %s).",
                    $data['telephone'],
                    $existingPhone->getPrenom(),
                    $existingPhone->getNom()
                ));
                return $this->render('admin/users/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            if (in_array('ROLE_ADMIN', $roles)) {
                $utilisateur = new \App\Entity\Admin();
            } else {
                $utilisateur = new \App\Entity\Evaluateur();
            }

            $utilisateur->setEmail($data['email']);
            $utilisateur->setNom(strtoupper($data['nom']));
            $utilisateur->setPrenom($data['prenom']);
            $utilisateur->setTelephone($data['telephone']);
            $utilisateur->setRoles($roles);

            // Génération d'un code d'accès unique
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $maxAttempts = 10;
            $accessCode = '';
            $attempt = 0;
            
            do {
                $accessCode = '';
                for ($i = 0; $i < 6; $i++) {
                    $accessCode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $attempt++;
                
                $existingCode = $entityManager->getRepository(Utilisateur::class)->findOneBy(['codeAcces' => $accessCode]);
                
                if ($attempt >= $maxAttempts) {
                    $accessCode .= substr(str_shuffle($alphabet), 0, 2);
                    break;
                }
            } while ($existingCode);
            
            $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $accessCode));
            $utilisateur->setCodeAcces($accessCode);
            $utilisateur->setDoitChangerMotDePasse(true);

            // Seul l'Admin Principal peut définir ces flags
            if ($isMainAdmin) {
                $canManageAdmins = $form->has('canManageAdmins') ? (bool) $form->get('canManageAdmins')->getData() : false;
                $isMainEvaluator = $form->has('isMainEvaluator') ? (bool) $form->get('isMainEvaluator')->getData() : false;
                
                $utilisateur->setCanManageAdmins($canManageAdmins);
                $utilisateur->setIsMainEvaluator($isMainEvaluator);
            }

            $entityManager->persist($utilisateur);

            // Envoyer l'email AVANT le flush
            $emailSent = true;
            try {
                $notificationService->sendAgentAccountEmail($utilisateur, $accessCode, true);
            } catch (\Exception $e) {
                $emailSent = false;
                $this->addFlash('error', sprintf(
                    "⚠️ Le compte a été créé mais l'email contenant le code d'accès n'a PAS pu être envoyé à %s. Code à communiquer manuellement : %s",
                    $utilisateur->getEmail(),
                    $accessCode
                ));
            }

            $entityManager->flush();

            if ($emailSent) {
                $this->addFlash('success', sprintf(
                    "Le compte de l'agent %s %s a été créé. Un email contenant son code d'accès initial lui a été envoyé à l'adresse : %s",
                    $utilisateur->getPrenom(),
                    $utilisateur->getNom(),
                    $utilisateur->getEmail()
                ));
            }

            return $this->redirectToRoute('app_admin_users_list');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        Utilisateur $utilisateur,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $isMainAdmin = $this->isMainAdmin();
        $isTargetAdmin = in_array('ROLE_ADMIN', $utilisateur->getRoles());

        if ($isTargetAdmin && !$isMainAdmin) {
            $this->addFlash('error', "Vous n'avez pas l'autorisation de modifier ce compte Administrateur.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        if ($this->isTargetMainAdmin($utilisateur) && !$isMainAdmin) {
            $this->addFlash('error', "Le compte Administrateur Principal est protégé.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        $form = $this->createForm(\App\Form\UserAdminType::class, $utilisateur, [
            'is_main_admin' => $isMainAdmin,
            'is_edit' => true,
            'is_authorized_to_manage_admins' => false
        ]);

        if ($isMainAdmin && $form->has('canManageAdmins')) {
            $form->get('canManageAdmins')->setData($utilisateur->canManageAdmins());
        }

        if ($isMainAdmin && $form->has('isMainEvaluator')) {
            $form->get('isMainEvaluator')->setData($utilisateur->isMainEvaluator());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isMainAdmin) {
                if ($form->has('canManageAdmins')) {
                    $utilisateur->setCanManageAdmins((bool) $form->get('canManageAdmins')->getData());
                }
                if ($form->has('isMainEvaluator')) {
                    $utilisateur->setIsMainEvaluator((bool) $form->get('isMainEvaluator')->getData());
                }
            }

            $entityManager->flush();
            $this->addFlash('success', "Le compte a été mis à jour.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $utilisateur
        ]);
    }

    #[Route('/supprimer/{id}', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        // Ne peut pas se supprimer soi-même
        if ($utilisateur === $this->getUser()) {
            $this->addFlash('error', "Vous ne pouvez pas supprimer votre propre compte.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $isMainAdmin = $this->isMainAdmin();

        // L'Admin Principal est protégé
        if ($this->isTargetMainAdmin($utilisateur)) {
            $this->addFlash('error', "Le compte Administrateur Principal est protégé et ne peut pas être supprimé.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        // Seul l'Admin Principal peut supprimer un Admin
        if (in_array('ROLE_ADMIN', $utilisateur->getRoles()) && !$isMainAdmin) {
            $this->addFlash('error', "Vous n'avez pas l'autorisation de supprimer un compte Administrateur.");
            return $this->redirectToRoute('app_admin_users_list');
        }

        // Vérifier si l'utilisateur a des évaluations
        $evaluationCount = $entityManager->getRepository(\App\Entity\Evaluation::class)
            ->count(['evaluateur' => $utilisateur]);

        // Vérifier si l'utilisateur a des dossiers assignés
        $dossierCount = $entityManager->getRepository(\App\Entity\Dossier::class)
            ->count(['evaluateur' => $utilisateur]);

        // Supprimer ou détacher les évaluations liées
        if ($evaluationCount > 0) {
            $entityManager->getRepository(\App\Entity\Evaluation::class)
                ->createQueryBuilder('e')
                ->delete()
                ->where('e.evaluateur = :evaluateur')
                ->setParameter('evaluateur', $utilisateur)
                ->getQuery()
                ->execute();
        }

        // Détacher les dossiers assignés
        if ($dossierCount > 0) {
            $entityManager->getRepository(\App\Entity\Dossier::class)
                ->createQueryBuilder('d')
                ->update()
                ->set('d.evaluateur', ':null')
                ->where('d.evaluateur = :evaluateur')
                ->setParameter('evaluateur', $utilisateur)
                ->setParameter('null', null)
                ->getQuery()
                ->execute();
        }

        // Supprimer les notifications de l'utilisateur
        $entityManager->getRepository(\App\Entity\Notification::class)
            ->createQueryBuilder('n')
            ->delete()
            ->where('n.receveur = :receveur')
            ->setParameter('receveur', $utilisateur)
            ->getQuery()
            ->execute();

        $entityManager->remove($utilisateur);
        $entityManager->flush();

        $message = "Le compte a été supprimé.";
        if ($evaluationCount > 0 || $dossierCount > 0) {
            $details = [];
            if ($evaluationCount > 0) {
                $details[] = $evaluationCount . ' évaluation(s) supprimée(s)';
            }
            if ($dossierCount > 0) {
                $details[] = $dossierCount . ' dossier(s) désassigné(s)';
            }
            $message .= ' (' . implode(', ', $details) . ').';
        }

        $this->addFlash('success', $message);
        return $this->redirectToRoute('app_admin_users_list');
    }
}