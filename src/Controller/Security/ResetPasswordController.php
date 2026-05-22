<?php

namespace App\Controller\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request, 
        EntityManagerInterface $entityManager, 
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if (!$user || in_array('ROLE_CANDIDAT', $user->getRoles())) {
                $this->logger->info('Tentative de récupération mot de passe échouée (utilisateur non trouvé ou candidat)', [
                    'email' => $email,
                ]);
                $this->addFlash('error', "Aucun compte expert ou administrateur n'est associé à cette adresse email.");
                return $this->redirectToRoute('app_forgot_password_request');
            }

            // Génération d'un code à 6 chiffres (valable 15 minutes)
            $code = (string) random_int(100000, 999999);
            $user->setResetToken($code);
            $user->setResetTokenExpiresAt(new \DateTimeImmutable('+15 minutes'));
            
            $entityManager->flush();

            // Envoi du mail avec le code
            try {
                $emailMessage = (new Email())
                    ->from($this->getParameter('app.admin_email') ?? 'no-reply@coursupreme.bj')
                    ->to($user->getEmail())
                    ->subject('Votre code de récupération - Cour Suprême')
                    ->html($this->renderView('emails/recovery_code_internal.html.twig', [
                        'user' => $user,
                        'code' => $code,
                    ]));

                $mailer->send($emailMessage);
                
                $this->logger->info('Code de récupération envoyé avec succès', [
                    'user_email' => $user->getEmail(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi du code de récupération', [
                    'user_email' => $user->getEmail(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', "Une erreur est survenue lors de l'envoi de l'email. Veuillez réessayer.");
                return $this->redirectToRoute('app_forgot_password_request');
            }

            // On stocke l'email en session pour la vérification
            $request->getSession()->set('reset_password_email', $email);

            $this->addFlash('success', "Un code de récupération a été envoyé à votre adresse email.");
            return $this->redirectToRoute('app_verify_recovery_code');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }

    #[Route('/verify-recovery-code', name: 'app_verify_recovery_code', methods: ['GET', 'POST'])]
    public function verifyCode(Request $request, EntityManagerInterface $entityManager): Response
    {
        $email = $request->getSession()->get('reset_password_email');
        if (!$email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy([
                'email' => $email,
                'resetToken' => $code
            ]);

            if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
                $this->logger->info('Tentative de vérification avec code invalide ou expiré', [
                    'email' => $email,
                ]);
                $this->addFlash('error', "Code invalide ou expiré.");
                return $this->render('security/verify_recovery_code.html.twig');
            }

            // Code valide : on prépare la redirection vers le reset
            $request->getSession()->set('recovery_authorized_email', $email);
            
            return $this->redirectToRoute('app_reset_password_after_code');
        }

        return $this->render('security/verify_recovery_code.html.twig');
    }

    #[Route('/reset-password-now', name: 'app_reset_password_after_code', methods: ['GET', 'POST'])]
    public function resetAfterCode(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $email = $request->getSession()->get('recovery_authorized_email');
        if (!$email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm_password');

            if ($password !== $confirm) {
                $this->addFlash('error', "Les mots de passe ne correspondent pas.");
                return $this->render('security/reset_password_after_code.html.twig');
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', "Le mot de passe doit faire au moins 8 caractères.");
                return $this->render('security/reset_password_after_code.html.twig');
            }

            // Mise à jour du mot de passe
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $user->setDoitChangerMotDePasse(false);
            
            try {
                $entityManager->flush();
                
                $this->logger->info('Mot de passe réinitialisé avec succès', [
                    'user_email' => $user->getEmail(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la réinitialisation du mot de passe', [
                    'user_email' => $user->getEmail(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', "Une erreur est survenue. Veuillez réessayer.");
                return $this->render('security/reset_password_after_code.html.twig');
            }

            // Nettoyage session
            $request->getSession()->remove('reset_password_email');
            $request->getSession()->remove('recovery_authorized_email');

            $this->addFlash('success', "Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.");
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_after_code.html.twig');
    }
}