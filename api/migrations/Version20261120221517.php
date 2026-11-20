<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120221517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journal_entry (id CHAR(36) NOT NULL, at DATETIME NOT NULL, author VARCHAR(160) NOT NULL, "action" VARCHAR(120) NOT NULL, suivi_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C8FAAE5A7FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C8FAAE5A7FEA59C06A57FD3C ON journal_entry (suivi_id, at)');
        $this->addSql('CREATE INDEX IDX_C8FAAE5A7FEA59C0 ON journal_entry (suivi_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE journal_entry');
    }
}
