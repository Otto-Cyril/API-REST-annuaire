<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'annuaire du personnel est lu directement dans l'AD : la table personne (saisie manuelle) n'a plus d'objet.
 */
final class Version20261001150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Supprime la table personne : l'annuaire du personnel est lu en direct dans l'AD";
    }

    public function preUp(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM personne') > 0,
            "La table personne contient des lignes : l'annuaire est désormais lu dans l'AD, ces données seraient perdues. Les vider (ou les exporter) avant la migration.",
        );
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE personne');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE personne (id INT IDENTITY NOT NULL, username NVARCHAR(50) NOT NULL, nom NVARCHAR(50) NOT NULL, prenom NVARCHAR(50) NOT NULL, email NVARCHAR(100), telephone NVARCHAR(50), dect NVARCHAR(50), service_id INT NOT NULL, metier_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FCEC9EFF85E0677 ON personne (username) WHERE username IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_FCEC9EFED5CA9E6 ON personne (service_id)');
        $this->addSql('CREATE INDEX IDX_FCEC9EFED16FA20 ON personne (metier_id)');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EFED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EFED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id)');
    }
}
