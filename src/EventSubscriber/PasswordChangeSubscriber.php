<?php

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PasswordChangeSubscriber implements EventSubscriberInterface
{
    private $router;
    private $tokenStorage;

    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Ne pas rediriger pour les routes de logout ou les routes de changement de profil elles-mêmes
        $excludedRoutes = [
            'app_logout',
            'app_admin_profile_settings',
            'app_login'
        ];

        if (in_array($route, $excludedRoutes)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        /** @var Utilisateur $user */
        $user = $token->getUser();

        if ($user instanceof Utilisateur && $user->isDoitChangerMotDePasse()) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_admin_profile_settings')));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
