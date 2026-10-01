<?php

namespace App\Ldap;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Annuaire du personnel lu dans l'AD : tous les comptes de l'unité d'organisation configurée sont chargés une fois,
 * gardés en cache (une heure par défaut), puis la recherche, les filtres, le tri et la pagination se font en mémoire.
 * Évite une requête LDAP à chaque frappe et garde un temps de réponse constant.
 */
class DirectoryCatalog
{
    public const SORT_NOM = 'nom';
    public const SORT_SERVICE = 'service';
    public const SORTS = [self::SORT_NOM, self::SORT_SERVICE];

    private const CACHE_KEY = 'directory.catalog.v2';
    private const PHOTO_KEY = 'directory.photo.';

    private static ?\Transliterator $transliterator = null;

    public function __construct(
        private readonly DirectoryLookupInterface $lookup,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {
    }

    /**
     * Efface le cache : le prochain appel relit l'AD.
     */
    public function refresh(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    public function find(string $username): ?DirectoryEntry
    {
        foreach ($this->rows() as $row) {
            if (0 === strcasecmp($row['entry']['username'], $username)) {
                return DirectoryEntry::fromArray($row['entry']);
            }
        }

        return null;
    }

    /**
     * Responsable et équipe d'un compte, résolus (DN -> identifiant et nom) parmi les comptes du catalogue.
     * Les DN qui ne sont pas dans l'annuaire du personnel (autre unité d'organisation, compte désactivé) sont ignorés.
     *
     * @return array{responsable: array{username: string, nom: string}|null, equipe: list<array{username: string, nom: string}>}
     */
    public function relations(DirectoryEntry $entry): array
    {
        $byDn = [];
        foreach ($this->rows() as $row) {
            if (null !== ($row['entry']['dn'] ?? null)) {
                $byDn[self::normalizeDn($row['entry']['dn'])] = DirectoryEntry::fromArray($row['entry']);
            }
        }

        $resolve = static function (string $dn) use ($byDn): ?array {
            $found = $byDn[self::normalizeDn($dn)] ?? null;

            return null === $found ? null : ['username' => $found->username, 'nom' => $found->fullName()];
        };

        $team = array_values(array_filter(array_map($resolve, $entry->reportDns)));
        usort($team, static fn (array $a, array $b) => self::fold($a['nom']) <=> self::fold($b['nom']));

        return [
            'responsable' => null === $entry->managerDn ? null : $resolve($entry->managerDn),
            'equipe' => $team,
        ];
    }

    /**
     * Photo du compte (octets bruts), lue dans l'AD à la demande puis gardée en cache ; null si le compte est inconnu ou sans photo.
     */
    public function photo(string $username): ?string
    {
        if (null === $entry = $this->find($username)) {
            return null;
        }

        $encoded = $this->cache->get(self::PHOTO_KEY.md5(strtolower($entry->username)), function (ItemInterface $item) use ($entry): string {
            $item->expiresAfter($this->ttl);

            return base64_encode($this->lookup->photo($entry->username) ?? '');
        });

        return '' === $encoded ? null : base64_decode($encoded, true);
    }

    /**
     * Services (attribut department) distincts, triés.
     *
     * @return list<string>
     */
    public function services(): array
    {
        return $this->distinct('department');
    }

    /**
     * Postes (attribut title) distincts, triés.
     *
     * @return list<string>
     */
    public function metiers(): array
    {
        return $this->distinct('title');
    }

    /**
     * Répartition du personnel : nombre de personnes par service (department) et par poste (title), du plus fréquent au moins
     * fréquent (à égalité, par ordre alphabétique) ; les personnes sans valeur ne sont pas comptées.
     *
     * @return array{total: int, services: list<array{libelle: string, total: int}>, metiers: list<array{libelle: string, total: int}>}
     */
    public function statistics(): array
    {
        $rows = $this->rows();

        return [
            'total' => count($rows),
            'services' => $this->count($rows, 'department'),
            'metiers' => $this->count($rows, 'title'),
        ];
    }

    /**
     * Chaque mot de $query doit se retrouver (sans tenir compte de la casse ni des accents) dans l'identifiant, le nom,
     * l'e-mail, le service, le poste ou un numéro. $service et $metier filtrent sur la valeur exacte.
     *
     * @return array{items: list<DirectoryEntry>, total: int}
     */
    public function search(?string $query, ?string $service, ?string $metier, string $sort, int $page, int $limit): array
    {
        $words = array_map([self::class, 'fold'], preg_split('/\s+/', trim((string) $query), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $found = array_filter($this->rows(), static function (array $row) use ($words, $service, $metier) {
            if (null !== $service && ($row['entry']['department'] ?? null) !== $service) {
                return false;
            }
            if (null !== $metier && ($row['entry']['title'] ?? null) !== $metier) {
                return false;
            }
            foreach ($words as $word) {
                if (!str_contains($row['haystack'], $word)) {
                    return false;
                }
            }

            return true;
        });

        $key = self::SORT_SERVICE === $sort ? 'sortService' : 'sortNom';
        usort($found, static fn (array $a, array $b) => [$a[$key], $a['entry']['username']] <=> [$b[$key], $b['entry']['username']]);

        return [
            'items' => array_map(static fn (array $row) => DirectoryEntry::fromArray($row['entry']), \array_slice($found, ($page - 1) * $limit, $limit)),
            'total' => \count($found),
        ];
    }

    /**
     * @param list<array{entry: array<string, mixed>}> $rows
     *
     * @return list<array{libelle: string, total: int}>
     */
    private function count(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if (null !== ($value = $row['entry'][$field] ?? null)) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }
        $items = [];
        foreach ($counts as $label => $total) {
            $items[] = ['libelle' => (string) $label, 'total' => $total];
        }
        usort($items, static fn (array $a, array $b) => [$b['total'], self::fold($a['libelle'])] <=> [$a['total'], self::fold($b['libelle'])]);

        return $items;
    }

    /**
     * @return list<string>
     */
    private function distinct(string $field): array
    {
        $values = [];
        foreach ($this->rows() as $row) {
            if (null !== ($value = $row['entry'][$field] ?? null)) {
                $values[$value] = true;
            }
        }
        $values = array_keys($values);
        usort($values, static fn (string $a, string $b) => self::fold($a) <=> self::fold($b));

        return $values;
    }

    /**
     * @return list<array{entry: array<string, mixed>, haystack: string, sortNom: string, sortService: string}>
     */
    private function rows(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter($this->ttl);

            $rows = [];
            foreach ($this->lookup->all() as $entry) {
                $nom = self::fold(($entry->nom ?? $entry->displayName ?? $entry->username).' '.($entry->prenom ?? ''));
                $rows[] = [
                    'entry' => $entry->toArray(),
                    'haystack' => self::fold(implode(' ', [
                        $entry->username, $entry->prenom, $entry->nom, $entry->displayName, $entry->email, $entry->department, $entry->title,
                        ...array_column($entry->numbers, 'numero'),
                    ])),
                    'sortNom' => $nom,
                    'sortService' => self::fold($entry->department ?? '~').' '.$nom,
                ];
            }

            return $rows;
        });
    }

    private static function normalizeDn(string $dn): string
    {
        return mb_strtolower(preg_replace('/\s*,\s*/', ',', trim($dn)) ?? $dn);
    }

    private static function fold(?string $text): string
    {
        self::$transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');

        return self::$transliterator?->transliterate((string) $text) ?: mb_strtolower((string) $text);
    }
}
