<?php

namespace App\Service;

use App\Entity\AppConfig;
use App\Entity\Dossier;
use App\Entity\Utilisateur;
use App\Entity\Notification;
use App\Enum\StatutDossier;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * SERVICE : NOTIFICATION (NotificationService)
 * Gère l'envoi des emails et les notifications internes.
 */
class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $adminEmail,
        private Environment $twig,
    ) {}

    /**
     * Envoie un email et crée une notification interne pour tous les agents.
     */
    public function notifyAdminsOfNewSubmission(Dossier $dossier): void
    {
        $agents = $this->utilisateurRepository->findAgents();
        $emails = array_filter(array_map(fn($agent) => $agent->getEmail(), $agents));

        $message = \sprintf(
            'Nouveau dossier de stage reçu : %s %s (%s) - %s',
            $dossier->getCandidat()->getNom(),
            $dossier->getCandidat()->getPrenom(),
            $dossier->getReference(),
            $dossier->getDomaine()
        );

        // 1. Email
        if (!empty($emails)) {
            $email = (new TemplatedEmail())
                ->from($this->adminEmail)
                ->to(...$emails)
                ->subject('Nouveau dossier de stage déposé - Cour Suprême')
                ->htmlTemplate('emails/new_submission_admin.html.twig')
                ->context(['dossier' => $dossier]);

            try {
                $this->mailer->send($email);
                $this->logger->info('Email nouvelle soumission envoyé à : ' . implode(', ', $emails));
            } catch (\Exception $e) {
                $this->logger->error('Erreur envoi email nouvelle soumission : ' . $e->getMessage());
            }
        }

        // 2. Notifications internes
        foreach ($agents as $agent) {
            $this->createInternalNotification($agent, $message);
        }
        $this->entityManager->flush();

        $this->logger->info('Notifications internes créées pour ' . \count($agents) . ' agents');
    }

    /**
     * Confirmation de dépôt au candidat
     */
    public function sendSubmissionConfirmation(Dossier $dossier): void
    {
        $user = $dossier->getCandidat();

        if (!$user) {
            $this->logger->warning('Tentative d\'envoi de confirmation sans candidat', [
                'dossier_ref' => $dossier->getReference(),
            ]);
            return;
        }

        if (!$user->getEmail()) {
            $this->logger->error('Impossible d\'envoyer la confirmation : email candidat manquant', [
                'dossier_ref' => $dossier->getReference(),
                'candidat_id' => $user->getId(),
            ]);
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject('Confirmation de dépôt de dossier - Cour Suprême')
            ->htmlTemplate('emails/submission_confirmation.html.twig')
            ->context([
                'dossier' => $dossier,
                'user' => $user,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de confirmation envoyé à : ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email confirmation : ' . $e->getMessage());
        }
    }

    /**
     * Envoi du code d'accès
     */
    public function sendAccessCode(Utilisateur $user): void
    {
        if (!$user->getEmail()) {
            $this->logger->error('Impossible d\'envoyer le code d\'accès : email utilisateur manquant', [
                'user_id' => $user->getId(),
            ]);
            throw new \InvalidArgumentException("L'email de l'utilisateur est manquant.");
        }

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject('Votre code d\'accès - Cour Suprême')
            ->htmlTemplate('emails/access_code.html.twig')
            ->context([
                'user' => $user,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email code d\'accès envoyé à : ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email code d\'accès : ' . $e->getMessage());
        }
    }

    /**
     * Mise à jour du statut (Accepté/Refusé/Mis en réserve)
     * Envoie un email au candidat ET crée des notifications internes pour les AUTRES agents seulement.
     */
    public function sendStatusUpdate(
        Dossier $dossier,
        #[Autowire(param: 'kernel.project_dir')] string $projectDir,
    ): void {
        $candidat = $dossier->getCandidat();

        $derniereEval = $dossier->getDerniereEvaluation();
        $motif = $derniereEval?->getCommentaire();
        $evaluateurQuiANote =  $derniereEval?->getEvaluateur();


        // 1. Email au candidat
        if ($candidat?->getEmail()) {
            // dd($candidat);
            $email = (new TemplatedEmail())
                ->from($this->adminEmail)
                ->to($candidat->getEmail())
                ->subject('Réponse à votre demande de stage - Cour Suprême')
                ->htmlTemplate('emails/status_update.html.twig')
                ->context([
                    'dossier' => $dossier,
                    'candidat' => $candidat,
                    'motif' => $motif,
                ]);

            try {

                if ($dossier->getStatut() === StatutDossier::VALIDE) {
                    $email->attach(
                        $this->generateAuthorizationPdf($dossier, $projectDir),
                        'Autorisation_Stage_CS.pdf',
                        'application/pdf'
                    );
                }
                $this->mailer->send($email);
                $this->logger->info('Email mise à jour statut envoyé à : ' . $candidat->getEmail());
            } catch (\Exception $e) {
                $this->logger->error('Erreur envoi email mise à jour statut : ' . $e->getMessage());
            }
        } else {
            $this->logger->warning('Impossible d\'envoyer la mise à jour de statut : email candidat manquant', [
                'dossier_ref' => $dossier->getReference(),
                'candidat_id' => $candidat->getId(),
            ]);
        }

        // 2. Notifications internes pour les AUTRES agents (pas celui qui a noté)
        $agents = $this->utilisateurRepository->findAgents();

        $statutLabel = $dossier->getStatut()->getLabel();
        $message = sprintf(
            'Dossier %s - %s %s : statut mis à jour → %s',
            $dossier->getReference(),
            $candidat ? $candidat->getNom() : 'Inconnu',
            $candidat ? $candidat->getPrenom() : '',
            $statutLabel
        );

        $count = 0;
        foreach ($agents as $agent) {
            // Ne pas notifier l'évaluateur qui a fait l'action lui-même
            if ($evaluateurQuiANote && $agent->getId() === $evaluateurQuiANote->getId()) {
                continue;
            }
            $this->createInternalNotification($agent, $message);
            $count++;
        }
        $this->entityManager->flush();

        $this->logger->info('Notifications de changement de statut créées pour ' . $count . ' agents (évaluateur exclu).');
    }

    /**
     * Notification quand un dossier est assigné à un évaluateur
     */
    public function sendAssignmentNotification(Dossier $dossier, Utilisateur $evaluateur): void
    {
        $candidat = $dossier->getCandidat();
        $message = sprintf(
            '📋 Nouveau dossier assigné : %s - %s %s (%s)',
            $dossier->getReference(),
            $candidat ? $candidat->getNom() : 'Inconnu',
            $candidat ? $candidat->getPrenom() : '',
            $dossier->getDomaine()
        );

        $this->createInternalNotification($evaluateur, $message);
        $this->entityManager->flush();

        if (!$evaluateur->getEmail()) {
            $this->logger->error('Impossible d\'envoyer la notification d\'assignation : email évaluateur manquant', [
                'evaluateur_id' => $evaluateur->getId(),
                'dossier_ref' => $dossier->getReference(),
            ]);
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->to($evaluateur->getEmail())
            ->subject('Nouveau dossier à évaluer - Cour Suprême')
            ->htmlTemplate('emails/new_submission_admin.html.twig')
            ->context(['dossier' => $dossier]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email d\'assignation envoyé à : ' . $evaluateur->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email assignation : ' . $e->getMessage());
        }
    }

    /**
     * Envoi des accès à un nouvel agent (Admin ou Évaluateur)
     */
    public function sendAgentAccountEmail(Utilisateur $user, string $password, bool $isNew = true): void
    {
        if (!$user->getEmail()) {
            $this->logger->error('Impossible d\'envoyer l\'email d\'accès agent : email manquant', [
                'user_id' => $user->getId(),
            ]);
            throw new \InvalidArgumentException("L'email de l'agent est manquant.");
        }

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject($isNew ? 'Bienvenue sur la plateforme - Vos accès sécurisés' : 'Réinitialisation de vos accès - Cour Suprême')
            ->htmlTemplate('emails/agent_password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetCode' => $password,
                'is_new_account' => $isNew
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email accès agent envoyé à : ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email accès agent : ' . $e->getMessage());
        }
    }

    /**
     * Crée une notification interne en base de données (sans flush).
     */
    private function createInternalNotification(Utilisateur $destinataire, string $message): void
    {
        $notification = new Notification();
        $notification->setReceveur($destinataire);
        $notification->setMessage($message);
        $notification->setDateEnvoi(new \DateTimeImmutable());
        $notification->setLu(false);

        $this->entityManager->persist($notification);
    }
    private function generateAuthorizationPdf(
        Dossier $dossier,
        #[Autowire(param: 'kernel.project_dir')] string $projectDir,
    ): string {
        $logoPath = $projectDir . '/public/images/logo_cour_supreme.jpeg';

        $logoBase64 = '';

        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);

            $logoBase64 = sprintf(
                'data:image/%s;base64,%s',
                $type,
                base64_encode($data)
            );
        }

        $signatureBase64 = $dossier->getSignatureOfficielle();
        $numeroOfficiel = $dossier->getNumeroOfficiel() ?: '________';

        $tamponConfig = $this->entityManager
            ->getRepository(AppConfig::class)
            ->findOneBy(['settingKey' => 'official_stamp']);

        $tamponBase64 = $tamponConfig?->getSettingValue();

        $signataireConfig = $this->entityManager
            ->getRepository(AppConfig::class)
            ->findOneBy(['settingKey' => 'signataire_nom']);

        $signataireNom = $signataireConfig?->getSettingValue()
            ?? 'François-Richard David KPENOU';

        if (!function_exists('imagecreatefrompng')) {
            if (
                $signatureBase64 &&
                str_contains($signatureBase64, 'image/png')
            ) {
                $signatureBase64 = null;
            }

            if (
                $logoBase64 &&
                str_contains($logoBase64, 'image/png')
            ) {
                $logoBase64 = '';
            }
        }

        $html = $this->twig->render(
            'emails/internship_authorization.html.twig',
            [
                'dossier' => $dossier,
                'logo_base64' => $logoBase64,
                'signature_base64' => $signatureBase64,
                'numero_officiel' => $numeroOfficiel,
                'signataire_nom' => $signataireNom,
                'tampon_base64' => $tamponBase64,
            ]
        );

        $options = new Options();
        $options->set('defaultFont', 'Times-Roman');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
