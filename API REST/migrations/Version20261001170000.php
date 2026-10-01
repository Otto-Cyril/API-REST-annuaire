<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le service et le métier du personnel de garde viennent de l'AD (department et title) : les tables service et metier
 * (référentiels saisis à la main) disparaissent, personnel_de_garde garde une copie en texte. Les libellés déjà
 * enregistrés sont conservés ; ils seront remplacés par ceux de l'AD au prochain app:ldap:sync.
 */
final class Version20261001170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Service et métier du personnel de garde en texte (copie de l\'AD) ; supprime les tables service et metier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnel_de_garde ADD service NVARCHAR(150)');
        $this->addSql('ALTER TABLE personnel_de_garde ADD metier NVARCHAR(150)');
        $this->addSql('UPDATE personnel_de_garde SET service = (SELECT s.libelle FROM service s WHERE s.id = personnel_de_garde.service_id), metier = (SELECT m.libelle FROM metier m WHERE m.id = personnel_de_garde.metier_id)');

        $this->addSql('ALTER TABLE personnel_de_garde DROP CONSTRAINT FK_7D100F7CED5CA9E6');
        $this->addSql('ALTER TABLE personnel_de_garde DROP CONSTRAINT FK_7D100F7CED16FA20');
        $this->addSql('DROP INDEX IDX_7D100F7CED5CA9E6 ON personnel_de_garde');
        $this->addSql('DROP INDEX IDX_7D100F7CED16FA20 ON personnel_de_garde');
        $this->addSql('ALTER TABLE personnel_de_garde DROP COLUMN service_id');
        $this->addSql('ALTER TABLE personnel_de_garde DROP COLUMN metier_id');

        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE metier');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les référentiels service et metier ne peuvent pas être reconstitués depuis des libellés en texte.');
    }
}
