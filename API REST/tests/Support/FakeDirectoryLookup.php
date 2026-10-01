<?php

namespace App\Tests\Support;

use App\Ldap\DirectoryEntry;
use App\Ldap\DirectoryLookupInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * AD simulé pour les tests (substitué à DirectoryLookup en environnement test, voir services.yaml).
 * L'état est statique : il survit au redémarrage du noyau entre deux requêtes d'un test.
 */
class FakeDirectoryLookup implements DirectoryLookupInterface
{
    /** @var array<string, DirectoryEntry> */
    private static array $accounts = [];
    /** @var array<string, string> */
    private static array $photos = [];
    private static bool $down = false;
    private static int $allCalls = 0;

    public static function reset(): void
    {
        self::$accounts = [];
        self::$photos = [];
        self::$down = false;
        self::$allCalls = 0;
    }

    /**
     * @param list<array{numero: string, type: string}> $numbers
     * @param list<string>                              $reports identifiants des personnes rattachées
     */
    public static function add(string $username, ?string $prenom = 'Jean', ?string $nom = 'Dupont', ?string $email = null, ?string $department = null, ?string $title = null, array $numbers = [], ?string $matricule = null, ?string $manager = null, array $reports = []): void
    {
        self::$accounts[strtolower($username)] = new DirectoryEntry(
            $username, $prenom, $nom, trim($prenom.' '.$nom), $email, $department, $title, $numbers,
            self::dn($username), $matricule, null === $manager ? null : self::dn($manager), array_map(self::dn(...), $reports),
        );
    }

    public static function photoOf(string $username, string $bytes): void
    {
        self::$photos[strtolower($username)] = $bytes;
    }

    private static function dn(string $username): string
    {
        return 'CN='.$username.',OU=Test,DC=imm,DC=local';
    }

    public static function down(bool $down = true): void
    {
        self::$down = $down;
    }

    /**
     * Nombre de lectures complètes de l'AD (pour vérifier la mise en cache).
     */
    public static function allCalls(): int
    {
        return self::$allCalls;
    }

    public function find(string $username): ?DirectoryEntry
    {
        $this->failIfDown();

        return self::$accounts[strtolower($username)] ?? null;
    }

    public function all(): array
    {
        $this->failIfDown();
        ++self::$allCalls;

        return array_values(self::$accounts);
    }

    public function photo(string $username): ?string
    {
        $this->failIfDown();

        return self::$photos[strtolower($username)] ?? null;
    }

    private function failIfDown(): void
    {
        if (self::$down) {
            throw new ServiceUnavailableHttpException(null, 'Annuaire LDAP indisponible.');
        }
    }
}
