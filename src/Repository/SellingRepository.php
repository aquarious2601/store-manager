<?php

namespace App\Repository;

use App\Entity\Selling;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Selling>
 *
 * @method Selling|null find($id, $lockMode = null, $lockVersion = null)
 * @method Selling|null findOneBy(array $criteria, array $orderBy = null)
 * @method Selling[]    findAll()
 * @method Selling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SellingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Selling::class);
    }

    public function invoiceNumberExists(string $invoiceNumber): bool
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.invoiceNumber = :invoiceNumber')
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
} 