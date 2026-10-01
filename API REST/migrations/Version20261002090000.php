<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pictogramme d'un numéro d'urgence, choisi par l'administrateur (null = déduit du libellé par l'interface).
 */
final class Version20261002090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute numero_urgence.icone (pictogramme choisi par l'administrateur, facultatif)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE numero_urgence ADD icone NVARCHAR(30)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE numero_urgence DROP COLUMN icone');
    }
}
