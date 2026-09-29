<?php

namespace App\Entity;

use App\Repository\TraceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TraceRepository::class)]
class Trace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trace:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['trace:read'])]
    private ?string $username = null;

    #[ORM\Column]
    #[Groups(['trace:read'])]
    private ?\DateTimeImmutable $dateAction = null;

    #[ORM\Column(length: 255)]
    #[Groups(['trace:read'])]
    private ?string $actionRealise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getDateAction(): ?\DateTimeImmutable
    {
        return $this->dateAction;
    }

    public function setDateAction(\DateTimeImmutable $dateAction): static
    {
        $this->dateAction = $dateAction;

        return $this;
    }

    public function getActionRealise(): ?string
    {
        return $this->actionRealise;
    }

    public function setActionRealise(string $actionRealise): static
    {
        $this->actionRealise = $actionRealise;

        return $this;
    }
}
