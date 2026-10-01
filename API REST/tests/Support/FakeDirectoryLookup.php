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
    private static bool $down = false;
    private static int $allCalls = 0;

    public static function reset(): void
    {
        self::$accounts = [];
        self::$down = false;
        self::$allCalls = 0;
    }

    /**
     * @param list<array{numero: string, type: string}> $numbers
     */
    public static function add(string $username, ?string $prenom = 'Jean', ?string $nom = 'Dupont', ?string $email = null, ?string $department = null, ?string $title = null, array $numbers = []): void
    {
        self::$accounts[strtolower($username)] = new DirectoryEntry($username, $prenom, $nom, trim($prenom.' '.$nom), $email, $department, $title, $numbers);
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

    private function failIfDown(): void
    {
        if (self::$down) {
            throw new ServiceUnavailableHttpException(null, 'Annuaire LDAP indisponible.');
        }
    }
}
