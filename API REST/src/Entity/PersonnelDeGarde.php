<?php

namespace App\Entity;

use App\Repository\PersonnelDeGardeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonnelDeGardeRepository::class)]
class PersonnelDeGarde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['personnel:read', 'numero_garde:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['personnel:read', 'personnel:write', 'numero_garde:read'])]
    private ?string $libelle = null;

    // service/metier sont résolus et affectés explicitement par le contrôleur
    // (à partir de serviceId/metierId reçus en entrée), pas désérialisés directement.
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'service_id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['personnel:read'])]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: Metier::class)]
    #[ORM\JoinColumn(name: 'metier_id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['personnel:read'])]
    private ?Metier $metier = null;

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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getMetier(): ?Metier
    {
        return $this->metier;
    }

    public function setMetier(?Metier $metier): static
    {
        $this->metier = $metier;

        return $this;
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
