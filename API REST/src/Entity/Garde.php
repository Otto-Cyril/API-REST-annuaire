<?php

namespace App\Entity;

use App\Repository\GardeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Créneau de garde d'une personne : du jour dateDebut au jour dateFin inclus
 * (une garde d'un seul jour a dateDebut = dateFin).
 */
#[ORM\Entity(repositoryClass: GardeRepository::class)]
class Garde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['garde:read'])]
    private ?int $id = null;

    // personnelDeGarde est résolu et affecté explicitement par le contrôleur
    // (à partir de personnelDeGardeId reçu en entrée), pas désérialisé directement.
    #[ORM\ManyToOne(targetEntity: PersonnelDeGarde::class)]
    #[ORM\JoinColumn(name: 'personnel_de_garde_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['garde:read'])]
    private ?PersonnelDeGarde $personnelDeGarde = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['garde:read', 'garde:write'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['garde:read', 'garde:write'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $dateFin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonnelDeGarde(): ?PersonnelDeGarde
    {
        return $this->personnelDeGarde;
    }

    public function setPersonnelDeGarde(?PersonnelDeGarde $personnelDeGarde): static
    {
        $this->personnelDeGarde = $personnelDeGarde;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    #[Assert\Callback]
    public function validatePeriode(ExecutionContextInterface $context): void
    {
        if (null !== $this->dateDebut && null !== $this->dateFin && $this->dateFin->format('Y-m-d') < $this->dateDebut->format('Y-m-d')) {
            $context->buildViolation('La date de fin doit être postérieure ou égale à la date de début.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}
