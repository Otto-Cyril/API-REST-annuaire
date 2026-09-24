<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\ExceptionInterface as LdapExceptionInterface;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentifie un utilisateur contre l'Active Directory (bind LDAP). Si un groupe AD "admin"
 * est configuré (LDAP_ADMIN_GROUP_DN non vide), seuls ses membres sont acceptés ; sinon tout
 * compte AD valide est accepté. En cas de succès, un JWT (ROLE_ADMIN) est émis, sinon 401.
 */
class LdapAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly LdapInterface $ldap,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly string $baseDn,
        private readonly string $searchDn,
        #[\SensitiveParameter] private readonly string $searchPassword,
        private readonly string $userQuery,
        private readonly string $adminGroupDn,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!\is_string($username) || !\is_string($password) || '' === $username || '' === $password) {
            throw new CustomUserMessageAuthenticationException('Identifiant et mot de passe requis.');
        }

        try {
            $this->ldap->bind($this->searchDn, $this->searchPassword);

            $escapedUsername = $this->ldap->escape($username, '', LdapInterface::ESCAPE_FILTER);
            $filter = str_replace('{username}', $escapedUsername, $this->userQuery);

            $results = $this->ldap->query($this->baseDn, $filter, ['filter' => ['dn', 'memberof']])->execute();

            if (0 === \count($results)) {
                throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
            }

            $entry = $results[0];
            $userDn = $entry->getDn();

            // Vérifie le mot de passe en effectuant un bind avec le DN trouvé
            $this->ldap->bind($userDn, $password);

            // Filtre de groupe optionnel : désactivé tant que LDAP_ADMIN_GROUP_DN est vide
            if ('' !== $this->adminGroupDn) {
                $memberOf = array_map('strtolower', $entry->getAttribute('memberOf') ?? []);
                if (!\in_array(strtolower($this->adminGroupDn), $memberOf, true)) {
                    throw new CustomUserMessageAuthenticationException('Ce compte n\'est pas autorisé à accéder à cette API.');
                }
            }
        } catch (ConnectionException) {
            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        } catch (LdapExceptionInterface $e) {
            throw new AuthenticationException('Erreur de connexion à l\'annuaire LDAP.', previous: $e);
        }

        return new SelfValidatingPassport(new UserBadge($username, fn () => new AdminUser($username)));
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        return new JsonResponse(['token' => $this->jwtManager->create($user)]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }
}
