<?php

namespace App\Repository;

use App\Entity\Dossier;
use App\Enum\StatutDossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    public function findDossierParStructure(): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('d.structure, COUNT(d.id) AS total')
            ->where('d.statut = :statut')
            ->andWhere('d.structure IS NOT NULL')
            ->setParameter('statut', StatutDossier::VALIDE)
            ->groupBy('d.structure')
            ->getQuery()
            ->getScalarResult();

        $indexed = [];
        foreach ($results as $row) {
            $key = $row['structure'] instanceof \BackedEnum
                ? $row['structure']->value
                : $row['structure'];
            $indexed[$key] = (int) $row['total'];
        }
        return $indexed;
    }
    //    /**
//     * @return Dossier[] Returns an array of Dossier objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Dossier
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
