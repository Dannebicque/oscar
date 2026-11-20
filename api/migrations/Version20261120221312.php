<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120221312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_token (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, label VARCHAR(80) NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA ON api_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBA76ED395 ON api_token (user_id)');
        $this->addSql('CREATE TABLE app_user (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, username VARCHAR(120) NOT NULL, display_name VARCHAR(160) NOT NULL, email VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE TABLE mail_account (id CHAR(36) NOT NULL, label VARCHAR(160) NOT NULL, address VARCHAR(180) NOT NULL, imap_host VARCHAR(255) NOT NULL, imap_port INTEGER NOT NULL, imap_encryption VARCHAR(10) NOT NULL, smtp_host VARCHAR(255) NOT NULL, smtp_port INTEGER NOT NULL, smtp_encryption VARCHAR(10) NOT NULL, username VARCHAR(180) NOT NULL, password_encrypted CLOB NOT NULL, ai_allowed BOOLEAN NOT NULL, last_sync_at DATETIME DEFAULT NULL, last_error CLOB DEFAULT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_A78BD7CB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A78BD7CB7E3C61F9 ON mail_account (owner_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail AS SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted FROM mail');
        $this->addSql('DROP TABLE mail');
        $this->addSql('CREATE TABLE mail (id CHAR(36) NOT NULL, folder VARCHAR(60) NOT NULL, uid INTEGER NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references CLOB NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses CLOB NOT NULL, subject VARCHAR(500) NOT NULL, received_at DATETIME NOT NULL, auto_submitted BOOLEAN NOT NULL, owner_id CHAR(36) NOT NULL, account_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5126AC487E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5126AC489B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO mail (id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted) SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted FROM __temp__mail');
        $this->addSql('DROP TABLE __temp__mail');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE INDEX IDX_5126AC487E3C61F9 ON mail (owner_id)');
        $this->addSql('CREATE INDEX IDX_5126AC489B6B5FBA ON mail (account_id)');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account_id, folder, uid)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suivi AS SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason FROM suivi');
        $this->addSql('DROP TABLE suivi');
        $this->addSql('CREATE TABLE suivi (id CHAR(36) NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INTEGER NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason CLOB DEFAULT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_2EBCCA8F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suivi (id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason) SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason FROM __temp__suivi');
        $this->addSql('DROP TABLE __temp__suivi');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7E3C61F9 ON suivi (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE mail_account');
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail AS SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted FROM mail');
        $this->addSql('DROP TABLE mail');
        $this->addSql('CREATE TABLE mail (id CHAR(36) NOT NULL, folder VARCHAR(60) NOT NULL, uid INTEGER NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references CLOB NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses CLOB NOT NULL, subject VARCHAR(500) NOT NULL, received_at DATETIME NOT NULL, auto_submitted BOOLEAN NOT NULL, account VARCHAR(120) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO mail (id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted) SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted FROM __temp__mail');
        $this->addSql('DROP TABLE __temp__mail');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account, folder, uid)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suivi AS SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason FROM suivi');
        $this->addSql('DROP TABLE suivi');
        $this->addSql('CREATE TABLE suivi (id CHAR(36) NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INTEGER NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason CLOB DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO suivi (id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason) SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason FROM __temp__suivi');
        $this->addSql('DROP TABLE __temp__suivi');
    }
}
