<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** État « lu » des mails. Les mails déjà synchronisés sont considérés lus ; la synchro suivante recale sur le serveur. */
final class Version20261126100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mail lu / non lu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail ADD seen BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail DROP seen');
    }
}
