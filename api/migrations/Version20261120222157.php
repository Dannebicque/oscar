<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120222157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_call (id CHAR(36) NOT NULL, at DATETIME NOT NULL, provider VARCHAR(60) NOT NULL, purpose VARCHAR(40) NOT NULL, input_summary VARCHAR(300) NOT NULL, output CLOB NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C8DF6E857E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C8DF6E857E3C61F96A57FD3C ON ai_call (owner_id, at)');
        $this->addSql('CREATE INDEX IDX_C8DF6E857E3C61F9 ON ai_call (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ai_call');
    }
}
