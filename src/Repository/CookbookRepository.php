<?php

namespace App\Repository;

use App\Entity\Cookbook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Cookbook|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cookbook|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cookbook[]    findAll()
 * @method Cookbook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CookbookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cookbook::class);
    }

    // /**
    //  * @return Cookbook[] Returns an array of Cookbook objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Cookbook
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
