<?php

namespace App\Ldap;

/**
 * Compte trouvé dans l'AD, avec les informations affichées dans l'annuaire.
 */
final class DirectoryEntry
{
    /**
     * @param list<array{numero: string, type: string}> $numbers  numéros professionnels du compte (les mobiles ne sont pas lus)
     * @param list<string>                              $reportDns DN des personnes rattachées à ce compte (directReports)
     */
    public function __construct(
        public readonly string $username,
        public readonly ?string $prenom,
        public readonly ?string $nom,
        public readonly ?string $displayName,
        public readonly ?string $email,
        public readonly ?string $department = null,
        public readonly ?string $title = null,
        public readonly array $numbers = [],
        public readonly ?string $dn = null,
        public readonly ?string $matricule = null,
        public readonly ?string $managerDn = null,
        public readonly array $reportDns = [],
    ) {
    }

    /**
     * « Prénom Nom », sinon le nom d'affichage de l'AD, sinon l'identifiant.
     */
    public function fullName(): string
    {
        $name = trim(($this->prenom ?? '').' '.($this->nom ?? ''));

        return '' !== $name ? $name : ($this->displayName ?: $this->username);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['username'], $data['prenom'], $data['nom'], $data['displayName'], $data['email'], $data['department'] ?? null, $data['title'] ?? null, $data['numbers'] ?? [], $data['dn'] ?? null, $data['matricule'] ?? null, $data['managerDn'] ?? null, $data['reportDns'] ?? []);
    }
}
