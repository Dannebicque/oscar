<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121175120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sort_rule (id UUID NOT NULL, enabled BOOLEAN NOT NULL, field VARCHAR(10) NOT NULL, rule_value VARCHAR(180) NOT NULL, action VARCHAR(10) NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX sort_rule_unique ON sort_rule (owner_id, field, rule_value)');
        $this->addSql('CREATE INDEX IDX_1077FCB67E3C61F9 ON sort_rule (owner_id)');
        $this->addSql('ALTER TABLE sort_rule ADD CONSTRAINT FK_1077FCB67E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sort_rule DROP CONSTRAINT FK_1077FCB67E3C61F9');
        $this->addSql('DROP TABLE sort_rule');
    }
}
