<?php

namespace App\Repository;

use App\Entity\Garde;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Garde>
 */
class GardeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Garde::class);
    }

    /**
     * Gardes couvrant le jour donné (bornes incluses), avec le personnel, son service, son métier et ses numéros.
     *
     * @return Garde[]
     */
    public function findActiveOn(\DateTimeImmutable $jour): array
    {
        return $this->withPersonnel()
            ->where('g.dateDebut <= :jour')
            ->andWhere('g.dateFin >= :jour')
            ->setParameter('jour', $jour, Types::DATE_IMMUTABLE)
            ->orderBy('p.libelle', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prochaine garde à venir : toutes les gardes commençant le premier jour de début postérieur au jour donné
     * (plusieurs personnes peuvent prendre leur garde le même jour). Liste vide s'il n'y en a aucune.
     *
     * @return Garde[]
     */
    public function findNextStartingAfter(\DateTimeImmutable $jour): array
    {
        $prochain = $this->createQueryBuilder('g')
            ->select('MIN(g.dateDebut)')
            ->where('g.dateDebut > :jour')
            ->setParameter('jour', $jour, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
        if (null === $prochain) {
            return [];
        }

        return $this->withPersonnel()
            ->where('g.dateDebut = :prochain')
            ->setParameter('prochain', new \DateTimeImmutable($prochain), Types::DATE_IMMUTABLE)
            ->orderBy('p.libelle', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les gardes, les plus récentes d'abord (écran d'administration).
     *
     * @return Garde[]
     */
    public function findAllOrdered(): array
    {
        return $this->withPersonnel()
            ->orderBy('g.dateDebut', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function withPersonnel(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->join('g.personnelDeGarde', 'p')->addSelect('p')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->leftJoin('p.metier', 'm')->addSelect('m')
            ->leftJoin('p.numerosGarde', 'n')->addSelect('n');
    }
}
