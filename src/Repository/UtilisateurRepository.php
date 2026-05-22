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
     * Trouve tous les utilisateurs ayant le rôle ROLE_EVALUATEUR uniquement.
     *
     * Cette méthode est utilisée par NotificationService pour envoyer
     * des emails de notification aux évaluateurs de la Cour Suprême.
     *
     * @return Utilisateur[] Tableau d'évaluateurs
     */
    public function findAgents(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :evaluateur')
            ->andWhere('u.roles NOT LIKE :candidat')
            ->setParameter('evaluateur', '%ROLE_EVALUATEUR%')
            ->setParameter('candidat', '%ROLE_CANDIDAT%')
            ->getQuery()
            ->getResult();
    }
}