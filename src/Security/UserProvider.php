<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UtilisateurRepository $repository)
    {
    }

    public function loadUserByIdentifier(string $identifier): Utilisateur
    {
        $user = $this->repository->findOneBy(['email' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('Utilisateur introuvable pour l\'email "%s".', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): Utilisateur
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances de "%s" non supportées.', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === Utilisateur::class || is_subclass_of($class, Utilisateur::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        $user->setPassword($newHashedPassword);
        $entityManager = $this->repository->getEntityManager();
        $entityManager->persist($user);
        $entityManager->flush();
    }
}
