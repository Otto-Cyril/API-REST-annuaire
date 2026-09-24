<?php

namespace App\Repository;

use App\Entity\PersonnelDeGarde;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonnelDeGarde>
 */
class PersonnelDeGardeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonnelDeGarde::class);
    }

    /**
     * @return Paginator<PersonnelDeGarde>
     */
    public function search(?string $q, ?int $serviceId, ?int $metierId, int $page, int $limit): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->leftJoin('p.metier', 'm')->addSelect('m')
            ->leftJoin('p.numerosGarde', 'n')->addSelect('n')
            ->orderBy('p.libelle', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $words = null === $q ? [] : preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $i => $word) {
            $escaped = addcslashes(mb_strtolower($word), '%_[\\');
            $qb->andWhere($qb->expr()->orX(
                sprintf('LOWER(p.libelle) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(s.libelle) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(s.localisation) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(m.libelle) LIKE :q%d ESCAPE \'\\\'', $i),
            ))->setParameter('q'.$i, '%'.$escaped.'%');
        }

        if (null !== $serviceId) {
            $qb->andWhere('s.id = :serviceId')->setParameter('serviceId', $serviceId);
        }

        if (null !== $metierId) {
            $qb->andWhere('m.id = :metierId')->setParameter('metierId', $metierId);
        }

        // fetchJoinCollection : la jointure sur numerosGarde multiplie les lignes,
        // le Paginator pagine donc sur les personnels et non sur les lignes jointes.
        return new Paginator($qb, true);
    }
}
