<?php

namespace App\Repository;

use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Personne>
 */
class PersonneRepository extends ServiceEntityRepository
{
    public const SORT_NOM = 'nom';
    public const SORT_SERVICE = 'service';
    public const SORTS = [self::SORT_NOM, self::SORT_SERVICE];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personne::class);
    }

    /**
     * @return Paginator<Personne>
     */
    public function search(?string $q, ?int $serviceId, ?int $metierId, int $page, int $limit, string $sort = self::SORT_NOM): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->leftJoin('p.metier', 'm')->addSelect('m')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Tri : par service puis nom, ou par nom (défaut) ; l'id départage les égalités pour une pagination stable.
        if (self::SORT_SERVICE === $sort) {
            $qb->orderBy('s.libelle', 'ASC')->addOrderBy('p.nom', 'ASC')->addOrderBy('p.prenom', 'ASC');
        } else {
            $qb->orderBy('p.nom', 'ASC')->addOrderBy('p.prenom', 'ASC');
        }
        $qb->addOrderBy('p.id', 'ASC');

        // Chaque mot doit se retrouver dans au moins un champ : « marie dupont » trouve Dupont Marie.
        $words = null === $q ? [] : preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $i => $word) {
            $escaped = addcslashes(mb_strtolower($word), '%_[\\');
            $qb->andWhere($qb->expr()->orX(
                sprintf('LOWER(p.nom) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.prenom) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.email) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.telephone) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.dect) LIKE :q%d ESCAPE \'\\\'', $i),
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

        return new Paginator($qb, false);
    }
}
