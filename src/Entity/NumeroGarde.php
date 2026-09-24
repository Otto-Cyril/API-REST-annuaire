<?php

namespace App\Entity;

use App\Repository\NumeroGardeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NumeroGardeRepository::class)]
class NumeroGarde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['personnel:read', 'numero_garde:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['personnel:read', 'numero_garde:read', 'numero_garde:write'])]
    private ?string $numero = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['personnel:read', 'numero_garde:read', 'numero_garde:write'])]
    private ?string $type = null;

    // personnelDeGarde est résolu et affecté explicitement par le contrôleur
    // (à partir de personnelDeGardeId reçu en entrée), pas désérialisé directement.
    #[ORM\ManyToOne(targetEntity: PersonnelDeGarde::class, inversedBy: 'numerosGarde')]
    #[ORM\JoinColumn(name: 'personnel_de_garde_id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['numero_garde:read'])]
    private ?PersonnelDeGarde $personnelDeGarde = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
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
}
