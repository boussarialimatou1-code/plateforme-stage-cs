<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            $targetUrl = $this->urlGenerator->generate('app_admin_users_list');
        } elseif (in_array('ROLE_EVALUATEUR', $roles, true)) {
            $targetUrl = $this->urlGenerator->generate('app_admin_dashboard');
        } else {
            $targetUrl = $this->urlGenerator->generate('app_home');
        }

        return new RedirectResponse($targetUrl);
    }
}
