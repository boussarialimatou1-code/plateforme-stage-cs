<?php

namespace App\Controller;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SetupAdminController extends AbstractController
{
    #[Route('/setup-admin-fix', name: 'app_setup_admin_fix')]
    public function index(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        #[Autowire(param: 'kernel.environment')]
        string $environment
    ): Response {
        // Cette route ne doit être accessible qu'en développement
        if ($environment !== 'dev') {
            throw $this->createNotFoundException('Cette route est désactivée en production.');
        }

        $existing = $em->getRepository(Admin::class)->findOneBy(['email' => 'admin@gmail.com']);
        if ($existing) {
            return new Response("L'admin admin@gmail.com existe déjà en base !");
        }

        // Récupération du mot de passe depuis la variable d'environnement
        $password = $_ENV['ADMIN_DEFAULT_PASSWORD'] ?? 'admin123';

        $admin = new Admin();
        $admin->setEmail('admin@gmail.com');
        $admin->setNom('ADMIN');
        $admin->setPrenom('System');
        $admin->setTelephone('0100000000');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_EVALUATEUR']);
        $admin->setPassword($hasher->hashPassword($admin, $password));

        $em->persist($admin);
        $em->flush();

        return new Response("Compte admin@gmail.com créé avec succès ! Vous pouvez maintenant tester le mot de passe oublié.");
    }
}