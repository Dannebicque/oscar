<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Chemins de dossiers IMAP plus longs (sous-dossiers). */
final class Version20261121212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mail.folder en 190 caractères';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail ALTER folder TYPE VARCHAR(190)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail ALTER folder TYPE VARCHAR(60)');
    }
}
