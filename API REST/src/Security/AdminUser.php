<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Représente un administrateur authentifié via l'AD.
 * Aucune persistance en base : tout utilisateur porteur d'un JWT valide
 * a déjà été validé comme membre du groupe admin AD lors du login.
 */
class AdminUser implements UserInterface
{
    public function __construct(private readonly string $username)
    {
    }

    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
