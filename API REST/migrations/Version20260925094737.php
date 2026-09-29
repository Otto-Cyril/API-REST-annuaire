<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * metier : username devient nom, ajout de prenom.
 */
final class Version20260925094737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'metier : renomme username en nom et ajoute prenom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('EXEC [sp_rename] N\'metier.username\', N\'nom\', N\'COLUMN\'');
        // Valeur par défaut temporaire pour que l'ajout d'une colonne NOT NULL réussisse sur une table déjà peuplée.
        $this->addSql('ALTER TABLE metier ADD prenom NVARCHAR(50) NOT NULL CONSTRAINT DF_metier_prenom DEFAULT \'\'');
        $this->addSql('ALTER TABLE metier DROP CONSTRAINT DF_metier_prenom');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE metier DROP COLUMN prenom');
        $this->addSql('EXEC [sp_rename] N\'metier.nom\', N\'username\', N\'COLUMN\'');
    }
}
