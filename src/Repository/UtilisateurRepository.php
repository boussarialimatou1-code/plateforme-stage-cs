<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Trouve tous les utilisateurs concernés par les notifications internes.
     *
     * Cette méthode est utilisée par NotificationService pour envoyer
     * des emails de notification aux évaluateurs et administrateurs.
     *
     * @return Utilisateur[] Tableau d'utilisateurs à notifier
     */
    public function findAgents(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('(u.roles LIKE :evaluateur OR u.roles LIKE :admin)')
            ->andWhere('u.roles NOT LIKE :candidat')
            ->setParameter('evaluateur', '%ROLE_EVALUATEUR%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->setParameter('candidat', '%ROLE_CANDIDAT%')
            ->getQuery()
            ->getResult();
    }
}