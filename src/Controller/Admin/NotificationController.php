<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/notifications')]
class NotificationController extends AbstractController
{
    #[Route('/marquer-toutes-lues', name: 'app_notifications_mark_all_read')]
    public function markAllRead(NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $notificationRepository->findUnreadForUser($user);

        foreach ($notifications as $notification) {
            $notification->setLu(true);
        }

        if (count($notifications) > 0) {
            $entityManager->flush();
        }

        return new RedirectResponse($this->generateUrl('app_admin_dashboard'));
    }

    #[Route('/marquer-lue/{id}', name: 'app_notifications_mark_read')]
    public function markRead(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user || $notification->getReceveur() !== $user) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $notification->setLu(true);
        $entityManager->flush();

        return new RedirectResponse($this->generateUrl('app_admin_dashboard'));
    }
}
