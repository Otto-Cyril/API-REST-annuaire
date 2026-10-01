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
    public const SORT_NOM = 'nom';
    public const SORT_SERVICE = 'service';
    public const SORTS = [self::SORT_NOM, self::SORT_SERVICE];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonnelDeGarde::class);
    }

    /**
     * $service et $metier : valeurs exactes (libellés de l'AD copiés en base).
     *
     * @return Paginator<PersonnelDeGarde>
     */
    public function search(?string $q, ?string $service, ?string $metier, int $page, int $limit, string $sort = self::SORT_NOM): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.numerosGarde', 'n')->addSelect('n')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Tri : par service puis nom, ou par nom (défaut) ; l'id départage les égalités pour une pagination stable.
        if (self::SORT_SERVICE === $sort) {
            $qb->orderBy('p.service', 'ASC')->addOrderBy('p.libelle', 'ASC');
        } else {
            $qb->orderBy('p.libelle', 'ASC');
        }
        $qb->addOrderBy('p.id', 'ASC');

        $words = null === $q ? [] : preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $i => $word) {
            $escaped = addcslashes(mb_strtolower($word), '%_[\\');
            $qb->andWhere($qb->expr()->orX(
                sprintf('LOWER(p.libelle) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.username) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.service) LIKE :q%d ESCAPE \'\\\'', $i),
                sprintf('LOWER(p.metier) LIKE :q%d ESCAPE \'\\\'', $i),
            ))->setParameter('q'.$i, '%'.$escaped.'%');
        }

        if (null !== $service) {
            $qb->andWhere('p.service = :service')->setParameter('service', $service);
        }

        if (null !== $metier) {
            $qb->andWhere('p.metier = :metier')->setParameter('metier', $metier);
        }

        // fetchJoinCollection : la jointure sur numerosGarde multiplie les lignes,
        // le Paginator pagine donc sur les personnels et non sur les lignes jointes.
        return new Paginator($qb, true);
    }

    /**
     * Valeurs distinctes (triées) du service ou du poste du personnel de garde, pour les filtres.
     *
     * @param 'service'|'metier' $field
     *
     * @return list<string>
     */
    public function distinct(string $field): array
    {
        $column = 'metier' === $field ? 'p.metier' : 'p.service';

        return array_column(
            $this->createQueryBuilder('p')->select("DISTINCT $column AS value")->where("$column IS NOT NULL")->orderBy($column, 'ASC')->getQuery()->getArrayResult(),
            'value',
        );
    }
}
