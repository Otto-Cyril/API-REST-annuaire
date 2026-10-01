<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Personne et PersonnelDeGarde : identifiant AD (username, unique), dont le nom, le prénom et l'e-mail sont recopiés.
 * metier : suppression de nom et prenom, sans rôle fonctionnel.
 */
final class Version20261001122453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute username (compte AD) à personne et personnel_de_garde, retire nom et prenom de metier';
    }

    public function preUp(Schema $schema): void
    {
        // Un username obligatoire et unique ne peut pas être deviné pour des lignes existantes.
        foreach (['personne', 'personnel_de_garde'] as $table) {
            $this->abortIf(
                (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table) > 0,
                sprintf('La table %s contient des lignes : les vider avant la migration, puis saisir à nouveau les personnes par leur identifiant AD.', $table),
            );
        }
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE metier DROP COLUMN nom');
        $this->addSql('ALTER TABLE metier DROP COLUMN prenom');
        $this->addSql('ALTER TABLE personne ADD username NVARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FCEC9EFF85E0677 ON personne (username) WHERE username IS NOT NULL');
        $this->addSql('ALTER TABLE personnel_de_garde ADD username NVARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D100F7CF85E0677 ON personnel_de_garde (username) WHERE username IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE metier ADD nom NVARCHAR(50) NOT NULL CONSTRAINT DF_metier_nom DEFAULT \'\'');
        $this->addSql('ALTER TABLE metier DROP CONSTRAINT DF_metier_nom');
        $this->addSql('ALTER TABLE metier ADD prenom NVARCHAR(50) NOT NULL CONSTRAINT DF_metier_prenom DEFAULT \'\'');
        $this->addSql('ALTER TABLE metier DROP CONSTRAINT DF_metier_prenom');
        $this->addSql('DROP INDEX UNIQ_FCEC9EFF85E0677 ON personne');
        $this->addSql('ALTER TABLE personne DROP COLUMN username');
        $this->addSql('DROP INDEX UNIQ_7D100F7CF85E0677 ON personnel_de_garde');
        $this->addSql('ALTER TABLE personnel_de_garde DROP COLUMN username');
    }
}
