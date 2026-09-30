<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Planning des gardes : table garde (personne de garde + période dateDebut/dateFin, bornes incluses).
 */
final class Version20260930125518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table garde (planning des gardes par jour)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE garde (id INT IDENTITY NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, personnel_de_garde_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5964B6CA18558D2 ON garde (personnel_de_garde_id)');
        $this->addSql('ALTER TABLE garde ADD CONSTRAINT FK_5964B6CA18558D2 FOREIGN KEY (personnel_de_garde_id) REFERENCES personnel_de_garde (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE garde DROP CONSTRAINT FK_5964B6CA18558D2');
        $this->addSql('DROP TABLE garde');
    }
}
