<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120221815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE log_entry (id CHAR(36) NOT NULL, at DATETIME NOT NULL, account VARCHAR(160) NOT NULL, type VARCHAR(30) NOT NULL, message CLOB NOT NULL, "action" VARCHAR(30) NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_B5F762D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B5F762D7E3C61F96A57FD3C ON log_entry (owner_id, at)');
        $this->addSql('CREATE INDEX IDX_B5F762D7E3C61F9 ON log_entry (owner_id)');
        $this->addSql('CREATE TABLE notification (id CHAR(36) NOT NULL, read BOOLEAN NOT NULL, at DATETIME NOT NULL, kind VARCHAR(30) NOT NULL, params CLOB NOT NULL, target VARCHAR(30) NOT NULL, dedupe_key VARCHAR(200) NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_BF5476CA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX notification_dedupe ON notification (owner_id, dedupe_key)');
        $this->addSql('CREATE INDEX IDX_BF5476CA7E3C61F9 ON notification (owner_id)');
        $this->addSql('CREATE TABLE user_settings (id CHAR(36) NOT NULL, "values" CLOB NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5C844C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C844C57E3C61F9 ON user_settings (owner_id)');
        $this->addSql('ALTER TABLE mail_account ADD COLUMN failure_count INTEGER NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE log_entry');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE user_settings');
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail_account AS SELECT id, label, address, imap_host, imap_port, imap_encryption, smtp_host, smtp_port, smtp_encryption, username, password_encrypted, ai_allowed, last_sync_at, last_error, owner_id FROM mail_account');
        $this->addSql('DROP TABLE mail_account');
        $this->addSql('CREATE TABLE mail_account (id CHAR(36) NOT NULL, label VARCHAR(160) NOT NULL, address VARCHAR(180) NOT NULL, imap_host VARCHAR(255) NOT NULL, imap_port INTEGER NOT NULL, imap_encryption VARCHAR(10) NOT NULL, smtp_host VARCHAR(255) NOT NULL, smtp_port INTEGER NOT NULL, smtp_encryption VARCHAR(10) NOT NULL, username VARCHAR(180) NOT NULL, password_encrypted CLOB NOT NULL, ai_allowed BOOLEAN NOT NULL, last_sync_at DATETIME DEFAULT NULL, last_error CLOB DEFAULT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_A78BD7CB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO mail_account (id, label, address, imap_host, imap_port, imap_encryption, smtp_host, smtp_port, smtp_encryption, username, password_encrypted, ai_allowed, last_sync_at, last_error, owner_id) SELECT id, label, address, imap_host, imap_port, imap_encryption, smtp_host, smtp_port, smtp_encryption, username, password_encrypted, ai_allowed, last_sync_at, last_error, owner_id FROM __temp__mail_account');
        $this->addSql('DROP TABLE __temp__mail_account');
        $this->addSql('CREATE INDEX IDX_A78BD7CB7E3C61F9 ON mail_account (owner_id)');
    }
}
