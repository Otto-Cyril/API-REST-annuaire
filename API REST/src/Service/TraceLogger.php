<?php

namespace App\Service;

use App\Entity\Trace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TraceLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * Exécute $operation (écriture + appels à log()) dans une seule transaction :
     * si la trace ne peut pas être enregistrée, la modification est annulée.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction($operation);
    }

    public function log(string $actionRealise): void
    {
        $trace = new Trace();
        $trace->setUsername($this->security->getUser()?->getUserIdentifier() ?? 'anonyme');
        $trace->setDateAction(new \DateTimeImmutable());
        $trace->setActionRealise($actionRealise);

        $this->entityManager->persist($trace);
        $this->entityManager->flush();
    }
}
