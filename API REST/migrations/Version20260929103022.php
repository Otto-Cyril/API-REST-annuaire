<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Annuaire général du personnel : table personne (distincte du personnel de garde).
 */
final class Version20260929103022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table personne (annuaire du personnel)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE personne (id INT IDENTITY NOT NULL, nom NVARCHAR(50) NOT NULL, prenom NVARCHAR(50) NOT NULL, email NVARCHAR(100), telephone NVARCHAR(50), service_id INT NOT NULL, metier_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FCEC9EFED5CA9E6 ON personne (service_id)');
        $this->addSql('CREATE INDEX IDX_FCEC9EFED16FA20 ON personne (metier_id)');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EFED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EFED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP CONSTRAINT FK_FCEC9EFED5CA9E6');
        $this->addSql('ALTER TABLE personne DROP CONSTRAINT FK_FCEC9EFED16FA20');
        $this->addSql('DROP TABLE personne');
    }
}
