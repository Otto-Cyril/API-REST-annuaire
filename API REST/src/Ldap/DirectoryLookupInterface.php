<?php

namespace App\Ldap;

interface DirectoryLookupInterface
{
    /**
     * Cherche un compte dans l'AD par son identifiant (sAMAccountName), sur toute la base LDAP.
     * Renvoie null s'il n'existe pas ; lève une 503 si l'annuaire est injoignable.
     */
    public function find(string $username): ?DirectoryEntry;

    /**
     * Tous les comptes actifs de l'annuaire du personnel (unité d'organisation LDAP_DIRECTORY_DN).
     * Lève une 503 si l'annuaire est injoignable.
     *
     * @return list<DirectoryEntry>
     */
    public function all(): array;

    /**
     * Photo (thumbnailPhoto, octets bruts) du compte, ou null s'il n'en a pas. Lève une 503 si l'annuaire est injoignable.
     */
    public function photo(string $username): ?string;
}
