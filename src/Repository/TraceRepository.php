<?php

namespace App\Repository;

use App\Entity\Trace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trace>
 */
class TraceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trace::class);
    }

    /**
     * Traces les plus récentes d'abord.
     *
     * @return Paginator<Trace>
     */
    public function paginate(int $page, int $limit): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.dateAction', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb, false);
    }
}
