<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Identités d'expéditeur (nom, adresse De, Répondre à, signature). */
final class Version20261122120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identités';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity (id UUID NOT NULL, is_default BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(100) NOT NULL, address VARCHAR(180) NOT NULL, reply_to VARCHAR(180) DEFAULT NULL, signature TEXT DEFAULT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, owner_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_IDENTITY_OWNER ON identity (owner_id)');
        $this->addSql('CREATE INDEX IDX_IDENTITY_ACCOUNT ON identity (account_id)');
        $this->addSql('ALTER TABLE identity ADD CONSTRAINT FK_IDENTITY_OWNER FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE identity ADD CONSTRAINT FK_IDENTITY_ACCOUNT FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity');
    }
}
