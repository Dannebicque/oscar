<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121150833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE follow_rule (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, enabled BOOLEAN NOT NULL, kind VARCHAR(10) NOT NULL, rule_value VARCHAR(180) NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX follow_rule_unique ON follow_rule (owner_id, kind, rule_value)');
        $this->addSql('CREATE INDEX IDX_781F5F917E3C61F9 ON follow_rule (owner_id)');
        $this->addSql('ALTER TABLE follow_rule ADD CONSTRAINT FK_781F5F917E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE follow_rule DROP CONSTRAINT FK_781F5F917E3C61F9');
        $this->addSql('DROP TABLE follow_rule');
    }
}
