<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findUnreadForUser($user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.receveur = :user')
            ->andWhere('n.lu = :lu')
            ->setParameter('user', $user)
            ->setParameter('lu', false)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

//    public function findOneBySomeField($value): ?Notification
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
