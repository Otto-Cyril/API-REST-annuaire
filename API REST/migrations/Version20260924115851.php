<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924115851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE metier (id INT IDENTITY NOT NULL, libelle NVARCHAR(50) NOT NULL, username NVARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE numero_garde (id INT IDENTITY NOT NULL, numero NVARCHAR(50) NOT NULL, type NVARCHAR(50) NOT NULL, personnel_de_garde_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AAC96051A18558D2 ON numero_garde (personnel_de_garde_id)');
        $this->addSql('CREATE TABLE numero_urgence (id INT IDENTITY NOT NULL, libelle NVARCHAR(50) NOT NULL, numero NVARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE personnel_de_garde (id INT IDENTITY NOT NULL, libelle NVARCHAR(50) NOT NULL, service_id INT NOT NULL, metier_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7D100F7CED5CA9E6 ON personnel_de_garde (service_id)');
        $this->addSql('CREATE INDEX IDX_7D100F7CED16FA20 ON personnel_de_garde (metier_id)');
        $this->addSql('CREATE TABLE service (id INT IDENTITY NOT NULL, libelle NVARCHAR(50) NOT NULL, localisation NVARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE trace (id INT IDENTITY NOT NULL, username NVARCHAR(50) NOT NULL, date_action DATETIME2(6) NOT NULL, action_realise NVARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE numero_garde ADD CONSTRAINT FK_AAC96051A18558D2 FOREIGN KEY (personnel_de_garde_id) REFERENCES personnel_de_garde (id)');
        $this->addSql('ALTER TABLE personnel_de_garde ADD CONSTRAINT FK_7D100F7CED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE personnel_de_garde ADD CONSTRAINT FK_7D100F7CED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE numero_garde DROP CONSTRAINT FK_AAC96051A18558D2');
        $this->addSql('ALTER TABLE personnel_de_garde DROP CONSTRAINT FK_7D100F7CED5CA9E6');
        $this->addSql('ALTER TABLE personnel_de_garde DROP CONSTRAINT FK_7D100F7CED16FA20');
        $this->addSql('DROP TABLE metier');
        $this->addSql('DROP TABLE numero_garde');
        $this->addSql('DROP TABLE numero_urgence');
        $this->addSql('DROP TABLE personnel_de_garde');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE trace');
    }
}
