<?php

namespace App\Tests\Ldap;

use App\Ldap\DirectoryLookup;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;

class DirectoryLookupTest extends TestCase
{
    private LdapInterface $ldap;

    protected function setUp(): void
    {
        $this->ldap = $this->createStub(LdapInterface::class);
        $this->ldap->method('escape')->willReturnArgument(0);
    }

    private function lookup(?LdapInterface $ldap = null, string $scope = 'one'): DirectoryLookup
    {
        return new DirectoryLookup($ldap ?? $this->ldap, 'OU=Comptes,DC=immdom,DC=local', 'CN=service,DC=immdom,DC=local', 'secret', '(sAMAccountName={username})', 'OU=Utilisateurs,OU=Comptes,DC=immdom,DC=local', $scope);
    }

    /** @param Entry[] $entries */
    private function ldapReturns(LdapInterface $ldap, array $entries): void
    {
        $collection = $this->createStub(CollectionInterface::class);
        $collection->method('count')->willReturn(\count($entries));
        $collection->method('offsetGet')->willReturnCallback(fn ($i) => $entries[$i]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($entries));
        $query = $this->createStub(QueryInterface::class);
        $query->method('execute')->willReturn($collection);
        $ldap->method('query')->willReturn($query);
    }

    public function testCompteTrouve(): void
    {
        $this->ldapReturns($this->ldap, [new Entry('CN=Jean Dupont,DC=immdom,DC=local', [
            'sAMAccountName' => ['jdupont'],
            'givenName' => ['Jean'],
            'sn' => ['Dupont'],
            'displayName' => ['Dupont Jean (IMM)'],
            'mail' => ['jean.dupont@imm.fr'],
            'department' => ['Cardiologie'],
            'title' => ['Médecin'],
        ])]);

        $account = $this->lookup()->find('JDUPONT');

        $this->assertSame('jdupont', $account->username); // identifiant canonique de l'AD
        $this->assertSame('Jean', $account->prenom);
        $this->assertSame('Dupont', $account->nom);
        $this->assertSame('jean.dupont@imm.fr', $account->email);
        $this->assertSame('Cardiologie', $account->department);
        $this->assertSame('Médecin', $account->title);
        $this->assertSame('Jean Dupont', $account->fullName());
    }

    public function testAttributsAbsentsOuVides(): void
    {
        $this->ldapReturns($this->ldap, [new Entry('CN=Service,DC=immdom,DC=local', ['sAMAccountName' => ['svc'], 'displayName' => ['Compte de service'], 'mail' => [' ']])]);

        $account = $this->lookup()->find('svc');

        $this->assertNull($account->prenom);
        $this->assertNull($account->nom);
        $this->assertNull($account->email);
        $this->assertNull($account->department);
        $this->assertSame([], $account->numbers);
        $this->assertSame('Compte de service', $account->fullName());
    }

    public function testCompteInconnu(): void
    {
        $this->ldapReturns($this->ldap, []);

        $this->assertNull($this->lookup()->find('inconnu'));
    }

    public function testTousLesNumerosSontRecuperesQuelQueSoitLeType(): void
    {
        $this->ldapReturns($this->ldap, [new Entry('CN=Jean,DC=immdom,DC=local', [
            'sAMAccountName' => ['jdupont'],
            'telephoneNumber' => ['4412,4413; 4412'],            // plusieurs numéros dans une valeur, doublon écarté
            'mobile' => ['06 12 34 56 78'],
            'ipPhone' => ['+33140000000'],
            'pager' => ['777'],
            'homePhone' => ['01 02 03 04 05'],                   // numéro personnel : jamais lu
        ])]);

        $numbers = $this->lookup()->find('jdupont')->numbers;

        $this->assertSame([
            ['numero' => '4412', 'type' => 'Tél.'],
            ['numero' => '4413', 'type' => 'Tél.'],
            ['numero' => '06 12 34 56 78', 'type' => 'Mobile'],
            ['numero' => '+33140000000', 'type' => 'IP'],
            ['numero' => '777', 'type' => 'Bip'],
        ], $numbers);
    }

    public function testAllLitLUniteDOrganisationDeLAnnuaireEtIgnoreLesComptesSansIdentifiant(): void
    {
        $captured = null;
        $collection = $this->createStub(CollectionInterface::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new Entry('CN=A', ['sAMAccountName' => ['adupont'], 'givenName' => ['Anne'], 'sn' => ['Dupont']]),
            new Entry('CN=Sans', ['givenName' => ['Sans'], 'sn' => ['Identifiant']]),
        ]));
        $query = $this->createStub(QueryInterface::class);
        $query->method('execute')->willReturn($collection);
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->expects($this->once())->method('query')->willReturnCallback(function (string $base, string $filter, array $options) use (&$captured, $query) {
            $captured = [$base, $filter, $options];

            return $query;
        });

        $entries = $this->lookup($ldap)->all();

        $this->assertSame(['adupont'], array_map(fn ($e) => $e->username, $entries));
        [$base, $filter, $options] = $captured;
        $this->assertSame('OU=Utilisateurs,OU=Comptes,DC=immdom,DC=local', $base);
        $this->assertStringContainsString('(!(userAccountControl:1.2.840.113556.1.4.803:=2))', $filter); // comptes désactivés exclus
        $this->assertSame('one', $options['scope']);
        $this->assertSame(0, $options['maxItems']);
        $this->assertNotContains('homephone', $options['filter']);
    }

    public function testAllPeutLireLesSousOu(): void
    {
        $collection = $this->createStub(CollectionInterface::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $query = $this->createStub(QueryInterface::class);
        $query->method('execute')->willReturn($collection);
        $ldap = $this->createMock(LdapInterface::class);
        $ldap->expects($this->once())->method('query')->with($this->anything(), $this->anything(), $this->callback(fn (array $o) => 'sub' === $o['scope']))->willReturn($query);

        $this->lookup($ldap, 'sub')->all();
    }

    public function testAnnuaireInjoignableRenvoieUne503(): void
    {
        $this->ldap->method('bind')->willThrowException(new ConnectionException('down'));

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Annuaire LDAP indisponible.');

        $this->lookup()->all();
    }
}
