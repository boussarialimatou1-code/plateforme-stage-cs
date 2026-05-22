<?php

namespace App\Twig;

use App\Enum\StatutDossier;
use App\Repository\DossierRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Repository\NotificationRepository;

class AdminExtension extends AbstractExtension
{
    public function __construct(
        private DossierRepository $dossierRepository,
        private NotificationRepository $notificationRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('count_pending_dossiers', [$this, 'getPendingCount']),
            new TwigFunction('get_unread_notifications', [$this, 'getUnreadNotifications']),
        ];
    }

    public function getUnreadNotifications($user): array
    {
        if (!$user) return [];
        return $this->notificationRepository->findUnreadForUser($user);
    }

    public function getPendingCount(): int
    {
        return $this->dossierRepository->count([
            'statut' => [
                StatutDossier::EN_ATTENTE, 
                StatutDossier::MIS_EN_RESERVE
            ]
        ]);
    }
}