<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Mail : date de la réponse envoyée depuis Oscar. */
final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mails : indicateur « répondu »';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail ADD answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail DROP answered_at');
    }
}
