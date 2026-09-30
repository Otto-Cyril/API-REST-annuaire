<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Annuaire du personnel : numéro DECT en plus du téléphone.
 */
final class Version20260930170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne dect (facultative) à la table personne';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne ADD dect NVARCHAR(50)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP COLUMN dect');
    }
}
