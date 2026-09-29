<?php

namespace App\Tests\Security;

use App\Security\AdminUser;
use App\Security\LdapAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class LdapAuthenticatorTest extends TestCase
{
    private const ADMIN_GROUP = 'CN=Admins,OU=Groupes,DC=immdom,DC=local';

    private LdapInterface $ldap;

    protected function setUp(): void
    {
        $this->ldap = $this->createStub(LdapInterface::class);
        $this->ldap->method('escape')->willReturnArgument(0);
    }

    private function authenticator(string $adminGroupDn = '', int $userLimit = 5, int $ipLimit = 30): LdapAuthenticator
    {
        $limiter = fn (int $limit) => new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '15 minutes'], new InMemoryStorage());

        return new LdapAuthenticator(
            $this->ldap,
            $this->createStub(JWTTokenManagerInterface::class),
            'OU=Comptes,DC=immdom,DC=local',
            'CN=service,DC=immdom,DC=local',
            'secret',
            '(sAMAccountName={username})',
            $adminGroupDn,
            $limiter($userLimit),
            $limiter($ipLimit),
        );
    }

    private function loginRequest(mixed $payload): Request
    {
        return Request::create('/api/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: \is_string($payload) ? $payload : json_encode($payload));
    }

    /** @param Entry[] $entries */
    private function ldapReturns(array $entries): void
    {
        $collection = $this->createStub(CollectionInterface::class);
        $collection->method('count')->willReturn(\count($entries));
        $collection->method('offsetGet')->willReturnCallback(fn ($i) => $entries[$i]);
        $query = $this->createStub(QueryInterface::class);
        $query->method('execute')->willReturn($collection);
        $this->ldap->method('query')->willReturn($query);
    }

    private function jdoe(array $memberOf = []): Entry
    {
        return new Entry('CN=John Doe,OU=Comptes,DC=immdom,DC=local', ['sAMAccountName' => ['jdoe'], 'memberOf' => $memberOf]);
    }

    private function assertAuthenticationFails(LdapAuthenticator $authenticator, Request $request, string $exceptionClass, ?string $message = null): void
    {
        try {
            $authenticator->authenticate($request);
            $this->fail('Une exception était attendue.');
        } catch (AuthenticationException $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            if (null !== $message) {
                $this->assertSame($message, $e->getMessageKey());
            }
        }
    }

    public function testCorpsInvalideEstRefuseSansAppelLdap(): void
    {
        $this->ldap = $this->createMock(LdapInterface::class);
        $this->ldap->expects($this->never())->method('bind');

        foreach (['"abc"', '123', 'pas du json', '{}', '{"username":"a"}', '{"username":"a","password":""}', '{"username":["a"],"password":"b"}'] as $body) {
            $this->assertAuthenticationFails($this->authenticator(), $this->loginRequest($body), CustomUserMessageAuthenticationException::class, 'Identifiant et mot de passe requis.');
        }
    }

    public function testSucces(): void
    {
        $this->ldapReturns([$this->jdoe()]);
        $binds = [];
        $this->ldap->method('bind')->willReturnCallback(function (string $dn, string $password) use (&$binds) {
            $binds[] = [$dn, $password];
        });

        $passport = $this->authenticator()->authenticate($this->loginRequest(['username' => 'JDOE', 'password' => 'motdepasse']));

        // 1) compte technique, 2) bind avec le DN de l'utilisateur et le mot de passe saisi
        $this->assertSame([['CN=service,DC=immdom,DC=local', 'secret'], ['CN=John Doe,OU=Comptes,DC=immdom,DC=local', 'motdepasse']], $binds);
        $user = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)->getUser();
        $this->assertInstanceOf(AdminUser::class, $user);
        // Identifiant canonique de l'AD, pas la casse saisie
        $this->assertSame('jdoe', $user->getUserIdentifier());
    }

    public function testUtilisateurInconnu(): void
    {
        $this->ldapReturns([]);

        $this->assertAuthenticationFails($this->authenticator(), $this->loginRequest(['username' => 'x', 'password' => 'y']), CustomUserMessageAuthenticationException::class, 'Identifiants invalides.');
    }

    public function testMauvaisMotDePasse(): void
    {
        $this->ldapReturns([$this->jdoe()]);
        $this->ldap->method('bind')->willReturnCallback(function (string $dn) {
            if (str_starts_with($dn, 'CN=John')) {
                throw new InvalidCredentialsException('bad');
            }
        });

        $this->assertAuthenticationFails($this->authenticator(), $this->loginRequest(['username' => 'jdoe', 'password' => 'faux']), CustomUserMessageAuthenticationException::class, 'Identifiants invalides.');
    }

    public function testAnnuaireInjoignableRenvoieUneErreurServeur(): void
    {
        $this->ldap->method('bind')->willThrowException(new ConnectionException('down'));

        $this->assertAuthenticationFails($this->authenticator(), $this->loginRequest(['username' => 'jdoe', 'password' => 'x']), AuthenticationServiceException::class);
    }

    public function testFiltreDeGroupeActif(): void
    {
        $this->ldapReturns([$this->jdoe(['CN=Autres,DC=x'])]);

        $this->assertAuthenticationFails($this->authenticator(self::ADMIN_GROUP), $this->loginRequest(['username' => 'jdoe', 'password' => 'x']), CustomUserMessageAuthenticationException::class);
    }

    public function testFiltreDeGroupeAccepteUnMembreSansTenirCompteDeLaCasse(): void
    {
        $this->ldapReturns([$this->jdoe([strtoupper(self::ADMIN_GROUP)])]);

        $passport = $this->authenticator(self::ADMIN_GROUP)->authenticate($this->loginRequest(['username' => 'jdoe', 'password' => 'x']));

        $this->assertNotNull($passport);
    }

    public function testLimitationParUtilisateur(): void
    {
        $this->ldapReturns([]);
        $authenticator = $this->authenticator(userLimit: 2);
        $request = $this->loginRequest(['username' => 'jdoe', 'password' => 'x']);

        $this->assertAuthenticationFails($authenticator, $request, CustomUserMessageAuthenticationException::class, 'Identifiants invalides.');
        $this->assertAuthenticationFails($authenticator, $request, CustomUserMessageAuthenticationException::class, 'Identifiants invalides.');
        $this->assertAuthenticationFails($authenticator, $request, TooManyLoginAttemptsAuthenticationException::class);
        // Un autre identifiant n'est pas bloqué par le compteur du premier
        $this->assertAuthenticationFails($authenticator, $this->loginRequest(['username' => 'autre', 'password' => 'x']), CustomUserMessageAuthenticationException::class, 'Identifiants invalides.');
    }

    public function testLimitationParAdresseIp(): void
    {
        $this->ldapReturns([]);
        $authenticator = $this->authenticator(ipLimit: 2);

        $this->assertAuthenticationFails($authenticator, $this->loginRequest(['username' => 'a', 'password' => 'x']), CustomUserMessageAuthenticationException::class);
        $this->assertAuthenticationFails($authenticator, $this->loginRequest(['username' => 'b', 'password' => 'x']), CustomUserMessageAuthenticationException::class);
        $this->assertAuthenticationFails($authenticator, $this->loginRequest(['username' => 'c', 'password' => 'x']), TooManyLoginAttemptsAuthenticationException::class);
    }

    public function testCodesHttpDesEchecs(): void
    {
        $authenticator = $this->authenticator();
        $request = $this->loginRequest([]);

        $this->assertSame(401, $authenticator->onAuthenticationFailure($request, new CustomUserMessageAuthenticationException('x'))->getStatusCode());
        $this->assertSame(429, $authenticator->onAuthenticationFailure($request, new TooManyLoginAttemptsAuthenticationException())->getStatusCode());
        $this->assertSame(503, $authenticator->onAuthenticationFailure($request, new AuthenticationServiceException('x'))->getStatusCode());
    }

    public function testSuccesRenvoieUnJwt(): void
    {
        $jwt = $this->createStub(JWTTokenManagerInterface::class);
        $jwt->method('create')->willReturn('le.jwt');
        $authenticator = new LdapAuthenticator($this->ldap, $jwt, '', '', '', '', '', new RateLimiterFactory(['id' => 'a', 'policy' => 'no_limit'], new InMemoryStorage()), new RateLimiterFactory(['id' => 'b', 'policy' => 'no_limit'], new InMemoryStorage()));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(new AdminUser('jdoe'));

        $response = $authenticator->onAuthenticationSuccess($this->loginRequest([]), $token, 'login');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['token' => 'le.jwt'], json_decode($response->getContent(), true));
    }
}
