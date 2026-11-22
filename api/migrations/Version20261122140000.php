<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Noms d'index alignés sur ceux que Doctrine génère (le schéma redevient valide). */
final class Version20261122140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Noms d’index des identités et des mails programmés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_identity_owner RENAME TO IDX_6A95E9C47E3C61F9');
        $this->addSql('ALTER INDEX idx_identity_account RENAME TO IDX_6A95E9C49B6B5FBA');
        $this->addSql('ALTER INDEX idx_schedmail_owner RENAME TO IDX_9EE7EB617E3C61F9');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_6A95E9C47E3C61F9 RENAME TO idx_identity_owner');
        $this->addSql('ALTER INDEX IDX_6A95E9C49B6B5FBA RENAME TO idx_identity_account');
        $this->addSql('ALTER INDEX IDX_9EE7EB617E3C61F9 RENAME TO idx_schedmail_owner');
    }
}
