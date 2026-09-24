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
