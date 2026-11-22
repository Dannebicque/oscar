<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Comptes désactivables et journal d'audit de l'instance. */
final class Version20261123110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Désactivation de comptes, journal d\'audit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD disabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE TABLE audit_entry (id UUID NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor VARCHAR(160) NOT NULL, action VARCHAR(40) NOT NULL, target VARCHAR(180) DEFAULT NULL, details JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2C6AF9876A57FD3C ON audit_entry (at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_entry');
        $this->addSql('ALTER TABLE app_user DROP disabled');
    }
}
