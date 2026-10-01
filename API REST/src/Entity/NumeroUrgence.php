<?php

namespace App\Entity;

use App\Repository\NumeroUrgenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NumeroUrgenceRepository::class)]
class NumeroUrgence
{
    /**
     * Pictogrammes proposés dans le formulaire (mêmes noms que frontend/src/components/Icon.vue et frontend/src/urgence.js).
     */
    public const ICONES = [
        'phone', 'plus', 'flame', 'shield', 'trash', 'user', 'heart', 'stethoscope',
        'alert', 'bed', 'pill', 'drop', 'building', 'wrench', 'ambulance', 'computer', 'virus', 'baby',
    ];

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

    // Pictogramme choisi par l'administrateur ; null = déduit du libellé par l'interface.
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(choices: self::ICONES, message: 'Icône inconnue.')]
    #[Groups(['numero_urgence:read', 'numero_urgence:write'])]
    private ?string $icone = null;

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

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(?string $icone): static
    {
        $this->icone = '' === $icone ? null : $icone;

        return $this;
    }
}
