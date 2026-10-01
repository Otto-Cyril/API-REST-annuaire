<?php

namespace App\Ldap;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ExceptionInterface as LdapExceptionInterface;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Lecture des comptes de l'AD avec le compte technique (même configuration que LdapAuthenticator).
 */
class DirectoryLookup implements DirectoryLookupInterface
{
    private const ATTRIBUTES = ['samaccountname', 'givenname', 'sn', 'displayname', 'mail', 'department', 'title', 'employeeid', 'manager', 'directreports'];

    // Numéros professionnels du compte. homePhone, mobile et otherMobile (numéros personnels) ne sont volontairement pas lus.
    private const NUMBER_ATTRIBUTES = [
        'telephonenumber' => 'Tél.',
        'othertelephone' => 'Tél.',
        'ipphone' => 'IP',
        'otheripphone' => 'IP',
        'pager' => 'Bip',
        'facsimiletelephonenumber' => 'Fax',
    ];

    private bool $bound = false;

    public function __construct(
        private readonly LdapInterface $ldap,
        private readonly string $baseDn,
        private readonly string $searchDn,
        #[\SensitiveParameter] private readonly string $searchPassword,
        private readonly string $userQuery,
        private readonly string $directoryDn,
        private readonly string $directoryScope = 'one',
    ) {
    }

    public function find(string $username): ?DirectoryEntry
    {
        $results = $this->query($this->baseDn, function () use ($username) {
            $escaped = $this->ldap->escape($username, '', LdapInterface::ESCAPE_FILTER);

            return str_replace('{username}', $escaped, $this->userQuery);
        });

        return [] === $results ? null : $this->toEntry($results[0], $username);
    }

    public function photo(string $username): ?string
    {
        $results = $this->query($this->baseDn, function () use ($username) {
            $escaped = $this->ldap->escape($username, '', LdapInterface::ESCAPE_FILTER);

            return str_replace('{username}', $escaped, $this->userQuery);
        }, [], ['thumbnailphoto']);

        $photo = [] === $results ? null : ($results[0]->getAttribute('thumbnailPhoto', false)[0] ?? null);

        return null === $photo || '' === $photo ? null : (string) $photo;
    }

    public function all(): array
    {
        // Comptes utilisateurs actifs (bit 2 d'userAccountControl = compte désactivé).
        $results = $this->query(
            $this->directoryDn,
            static fn () => '(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))',
            ['scope' => 'sub' === $this->directoryScope ? 'sub' : 'one', 'maxItems' => 0, 'pageSize' => 500],
        );

        $entries = [];
        foreach ($results as $result) {
            if (null !== $username = self::attribute($result, 'sAMAccountName')) {
                $entries[] = $this->toEntry($result, $username);
            }
        }

        return $entries;
    }

    /**
     * @param callable(): string   $filter construit le filtre (l'échappement LDAP peut lever une erreur)
     * @param array<string, mixed> $options
     * @param list<string>|null    $attributes attributs à lire (par défaut : ceux de la fiche)
     *
     * @return list<Entry>
     */
    private function query(string $dn, callable $filter, array $options = [], ?array $attributes = null): array
    {
        try {
            // Un seul bind par instance : la commande de synchronisation interroge de nombreux comptes.
            if (!$this->bound) {
                $this->ldap->bind($this->searchDn, $this->searchPassword);
                $this->bound = true;
            }

            $attributes ??= [...self::ATTRIBUTES, ...array_keys(self::NUMBER_ATTRIBUTES)];

            return iterator_to_array($this->ldap->query($dn, $filter(), ['filter' => $attributes] + $options)->execute(), false);
        } catch (LdapExceptionInterface $e) {
            $this->bound = false;

            throw new ServiceUnavailableHttpException(null, 'Annuaire LDAP indisponible.', $e);
        }
    }

    private function toEntry(Entry $entry, string $fallbackUsername): DirectoryEntry
    {
        return new DirectoryEntry(
            self::attribute($entry, 'sAMAccountName') ?? $fallbackUsername,
            self::attribute($entry, 'givenName'),
            self::attribute($entry, 'sn'),
            self::attribute($entry, 'displayName'),
            self::attribute($entry, 'mail'),
            self::attribute($entry, 'department'),
            self::attribute($entry, 'title'),
            self::numbers($entry),
            $entry->getDn(),
            self::attribute($entry, 'employeeID'),
            self::attribute($entry, 'manager'),
            array_values(array_filter(array_map(static fn ($dn) => trim((string) $dn), $entry->getAttribute('directReports', false) ?? []), static fn (string $dn) => '' !== $dn)),
        );
    }

    /**
     * @return list<array{numero: string, type: string}>
     */
    private static function numbers(Entry $entry): array
    {
        $numbers = [];
        foreach (self::NUMBER_ATTRIBUTES as $attribute => $type) {
            foreach ($entry->getAttribute($attribute, false) ?? [] as $value) {
                // Plusieurs numéros peuvent être saisis dans une même valeur (« 1234,5678 »).
                foreach (preg_split('/[,;]/', (string) $value) ?: [] as $number) {
                    $number = trim($number);
                    $key = preg_replace('/[\s.\-()]/', '', $number);
                    // Un mobile saisi dans un autre champ (06, 07, +33 6/7) reste un numéro personnel : écarté.
                    if ('' !== $number && !preg_match('/^(\+?33|0033|0)[67]\d{8}$/', $key) && !isset($numbers[$key])) {
                        $numbers[$key] = ['numero' => $number, 'type' => $type];
                    }
                }
            }
        }

        return array_values($numbers);
    }

    private static function attribute(Entry $entry, string $name): ?string
    {
        $value = $entry->getAttribute($name, false)[0] ?? null;

        return null === $value || '' === trim((string) $value) ? null : trim((string) $value);
    }
}
