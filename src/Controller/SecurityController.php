<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/espace-interne-securise', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, rediriger selon le rôle
        $user = $this->getUser();
        if ($user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true)) {
                return $this->redirectToRoute('app_admin_users_list');
            }
            if (in_array('ROLE_EVALUATEUR', $roles, true)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastEmail,
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}