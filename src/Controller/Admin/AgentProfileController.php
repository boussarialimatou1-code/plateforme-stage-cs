<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur de gestion du profil pour les Agents (Administrateurs et Experts Évaluateurs).
 */
#[Route('/admin/profil')]
#[IsGranted('ROLE_USER')]
class AgentProfileController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/identifiants', name: 'app_admin_profile_settings', methods: ['GET', 'POST'])]
    public function changeCredentials(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $isForced = $user->isDoitChangerMotDePasse();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('verify_p');
            $newEmail = $request->request->get('update_email');
            $newPassword = $request->request->get('update_p');
            $confirmPassword = $request->request->get('update_pc');

            // Vérification du mot de passe actuel (Sauf si c'est une première connexion/reset)
            if (!$isForced) {
                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->logger->warning('Tentative de changement de mot de passe avec un mauvais mot de passe actuel', [
                        'user_id' => $user->getId(),
                        'user_email' => $user->getEmail(),
                    ]);
                    $this->addFlash('error', "Le mot de passe actuel est incorrect.");
                    return $this->redirectToRoute('app_admin_profile_settings');
                }
            }

            // Mise à jour de l'email
            if ($newEmail && $newEmail !== $user->getEmail()) {
                // Vérifier si l'email est déjà utilisé par un AUTRE utilisateur
                $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $newEmail]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', "Cette adresse email est déjà utilisée par un autre compte.");
                    return $this->redirectToRoute('app_admin_profile_settings');
                }
                $user->setEmail($newEmail);
            }

            if ($newPassword) {
                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', "Les nouveaux mots de passe ne correspondent pas.");
                    return $this->redirectToRoute('app_admin_profile_settings');
                }
                if (strlen($newPassword) < 8) {
                    $this->addFlash('error', "Le nouveau mot de passe doit faire au moins 8 caractères.");
                    return $this->redirectToRoute('app_admin_profile_settings');
                }
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }

            // Marque que le changement obligatoire a été effectué
            $user->setDoitChangerMotDePasse(false);

            try {
                $entityManager->flush();
                
                $this->logger->info('Profil utilisateur mis à jour avec succès', [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail(),
                    'email_changed' => $newEmail && $newEmail !== $user->getEmail(),
                    'password_changed' => !empty($newPassword),
                ]);
                
                $this->addFlash('success', "Vos identifiants ont été mis à jour.");
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la mise à jour du profil utilisateur', [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', "Une erreur est survenue lors de la mise à jour de vos identifiants. Veuillez réessayer.");
                return $this->redirectToRoute('app_admin_profile_settings');
            }
            
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin_users_list');
            }

            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/profile/evaluator_settings.html.twig');
    }
}