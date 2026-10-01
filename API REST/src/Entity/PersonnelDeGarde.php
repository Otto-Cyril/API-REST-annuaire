<?php

namespace App\Entity;

use App\Repository\PersonnelDeGardeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le libellé (« Prénom Nom ») est recopié depuis l'AD à partir du username : jamais saisi à la main.
 */
#[ORM\Entity(repositoryClass: PersonnelDeGardeRepository::class)]
#[UniqueEntity(fields: ['username'], message: 'Cet identifiant AD est déjà enregistré.')]
class PersonnelDeGarde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['personnel:read', 'numero_garde:read'])]
    private ?int $id = null;

    // Identifiant du compte AD (sAMAccountName) : seule donnée saisie, le reste vient de l'AD.
    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['personnel:read', 'personnel:write'])]
    private ?string $username = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['personnel:read', 'numero_garde:read'])]
    private ?string $libelle = null;

    // Service et poste : copies de department et title de l'AD, affectés par le contrôleur (jamais saisis).
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $metier = null;

    /**
     * @var Collection<int, NumeroGarde>
     */
    #[ORM\OneToMany(targetEntity: NumeroGarde::class, mappedBy: 'personnelDeGarde', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['personnel:read'])]
    private Collection $numerosGarde;

    public function __construct()
    {
        $this->numerosGarde = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
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

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getMetier(): ?string
    {
        return $this->metier;
    }

    public function setMetier(?string $metier): static
    {
        $this->metier = $metier;

        return $this;
    }

    /**
     * Service tel que l'API le sert : { id, libelle } (id = libelle, le service est un libellé de l'AD), ou null.
     *
     * @return array{id: string, libelle: string}|null
     */
    #[Groups(['personnel:read'])]
    #[SerializedName('service')]
    public function getServiceInfo(): ?array
    {
        return null === $this->service ? null : ['id' => $this->service, 'libelle' => $this->service];
    }

    /**
     * @return array{id: string, libelle: string}|null
     */
    #[Groups(['personnel:read'])]
    #[SerializedName('metier')]
    public function getMetierInfo(): ?array
    {
        return null === $this->metier ? null : ['id' => $this->metier, 'libelle' => $this->metier];
    }

    /**
     * @return Collection<int, NumeroGarde>
     */
    public function getNumerosGarde(): Collection
    {
        return $this->numerosGarde;
    }

    public function addNumeroGarde(NumeroGarde $numeroGarde): static
    {
        if (!$this->numerosGarde->contains($numeroGarde)) {
            $this->numerosGarde->add($numeroGarde);
            $numeroGarde->setPersonnelDeGarde($this);
        }

        return $this;
    }

    public function removeNumeroGarde(NumeroGarde $numeroGarde): static
    {
        if ($this->numerosGarde->removeElement($numeroGarde)) {
            if ($numeroGarde->getPersonnelDeGarde() === $this) {
                $numeroGarde->setPersonnelDeGarde(null);
            }
        }

        return $this;
    }
}
