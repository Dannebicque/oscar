<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121103826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_provider_config (id UUID NOT NULL, kind VARCHAR(20) NOT NULL, base_url VARCHAR(255) NOT NULL, model VARCHAR(120) NOT NULL, api_key_encrypted TEXT DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6F0A027A7E3C61F9 ON ai_provider_config (owner_id)');
        $this->addSql('ALTER TABLE ai_provider_config ADD CONSTRAINT FK_6F0A027A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_provider_config DROP CONSTRAINT FK_6F0A027A7E3C61F9');
        $this->addSql('DROP TABLE ai_provider_config');
    }
}
