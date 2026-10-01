<?php

namespace App\Ldap;

/**
 * Compte trouvé dans l'AD, avec les informations affichées dans l'annuaire.
 */
final class DirectoryEntry
{
    /**
     * @param list<array{numero: string, type: string}> $numbers tous les numéros du compte, quel que soit leur type
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
        return new self($data['username'], $data['prenom'], $data['nom'], $data['displayName'], $data['email'], $data['department'] ?? null, $data['title'] ?? null, $data['numbers'] ?? []);
    }
}
