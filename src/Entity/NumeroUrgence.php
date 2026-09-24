<?php

namespace App\Entity;

use App\Repository\NumeroUrgenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NumeroUrgenceRepository::class)]
class NumeroUrgence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['numero_urgence:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['numero_urgence:read', 'numero_urgence:write'])]
    private ?string $libelle = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['numero_urgence:read', 'numero_urgence:write'])]
    private ?string $numero = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
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
}
