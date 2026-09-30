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
     * Traces les plus récentes d'abord, filtrables : utilisateur (contient), type d'action (début du libellé)
     * et période (bornes incluses).
     *
     * @return Paginator<Trace>
     */
    public function paginate(int $page, int $limit, ?string $username = null, ?string $action = null, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.dateAction', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $username) {
            $qb->andWhere("LOWER(t.username) LIKE :username ESCAPE '\\'")->setParameter('username', '%'.addcslashes(mb_strtolower($username), '%_\\').'%');
        }
        if (null !== $action) {
            $qb->andWhere('t.actionRealise LIKE :action')->setParameter('action', $action.'%');
        }
        if (null !== $from) {
            $qb->andWhere('t.dateAction >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('t.dateAction < :to')->setParameter('to', $to->modify('+1 day'));
        }

        return new Paginator($qb, false);
    }
}
