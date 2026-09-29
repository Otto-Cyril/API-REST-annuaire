<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Reconstruit l'utilisateur admin à partir de l'identifiant porté par le JWT,
 * sans nouvelle requête LDAP : seuls les comptes admin AD reçoivent un JWT (voir LdapAuthenticator).
 */
class JwtUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new AdminUser($identifier);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AdminUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return new AdminUser($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return AdminUser::class === $class;
    }
}
