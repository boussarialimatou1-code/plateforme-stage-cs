<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée ou met à jour le compte administrateur principal',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private string $superAdminEmail,
        private string $adminDefaultPassword,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $this->superAdminEmail;
        $password = $this->adminDefaultPassword;

        // En production, avertissement si mot de passe par défaut inchangé
        if ($this->environment === 'prod' && $password === 'admin123') {
            $io->warning('ATTENTION : Vous utilisez le mot de passe par défaut en production ! Changez ADMIN_DEFAULT_PASSWORD dans le fichier .env.local');
        }

        $adminRepository = $this->entityManager->getRepository(Admin::class);
        $admin = $adminRepository->findOneBy(['email' => $email]);

        if (!$admin) {
            $io->info('Création du nouvel administrateur...');
            $admin = new Admin();
            $admin->setEmail($email);
        } else {
            $io->info('Mise à jour de l\'administrateur existant...');
        }

        $admin->setRoles(['ROLE_ADMIN', 'ROLE_EVALUATEUR']);
        $admin->setNom('ADMIN');
        $admin->setPrenom('Principal');
        $admin->setCanManageAdmins(true);
        $admin->setIsMainEvaluator(true);

        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setPassword($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success([
            'Identifiants admin opérationnels :',
            'Email    : ' . $email,
            'Password : ' . $password,
            'Roles    : ROLE_ADMIN, ROLE_EVALUATEUR'
        ]);

        return Command::SUCCESS;
    }
}