<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120222431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shared_mailbox (id CHAR(36) NOT NULL, address VARCHAR(180) NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(60) NOT NULL, auto_follow BOOLEAN NOT NULL, ai_enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8748EEBCD4E6F81 ON shared_mailbox (address)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8748EEBC989D9B62 ON shared_mailbox (slug)');
        $this->addSql('CREATE TABLE shared_mailbox_member (id CHAR(36) NOT NULL, role VARCHAR(10) NOT NULL, mailbox_id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5A41DFD766EC35CC FOREIGN KEY (mailbox_id) REFERENCES shared_mailbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A41DFD7A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX shared_member ON shared_mailbox_member (mailbox_id, user_id)');
        $this->addSql('CREATE INDEX IDX_5A41DFD766EC35CC ON shared_mailbox_member (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_5A41DFD7A76ED395 ON shared_mailbox_member (user_id)');
        $this->addSql('CREATE TABLE suivi_note (id CHAR(36) NOT NULL, at DATETIME NOT NULL, author VARCHAR(160) NOT NULL, text CLOB NOT NULL, suivi_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_7F9DAD767FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7F9DAD767FEA59C0 ON suivi_note (suivi_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail AS SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id FROM mail');
        $this->addSql('DROP TABLE mail');
        $this->addSql('CREATE TABLE mail (id CHAR(36) NOT NULL, folder VARCHAR(60) NOT NULL, uid INTEGER NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references CLOB NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses CLOB NOT NULL, subject VARCHAR(500) NOT NULL, received_at DATETIME NOT NULL, auto_submitted BOOLEAN NOT NULL, owner_id CHAR(36) NOT NULL, account_id CHAR(36) NOT NULL, shared_mailbox_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_5126AC487E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5126AC489B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5126AC4812FFD503 FOREIGN KEY (shared_mailbox_id) REFERENCES shared_mailbox (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO mail (id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id) SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id FROM __temp__mail');
        $this->addSql('DROP TABLE __temp__mail');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account_id, folder, uid)');
        $this->addSql('CREATE INDEX IDX_5126AC489B6B5FBA ON mail (account_id)');
        $this->addSql('CREATE INDEX IDX_5126AC487E3C61F9 ON mail (owner_id)');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE INDEX IDX_5126AC4812FFD503 ON mail (shared_mailbox_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suivi AS SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id FROM suivi');
        $this->addSql('DROP TABLE suivi');
        $this->addSql('CREATE TABLE suivi (id CHAR(36) NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INTEGER NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason CLOB DEFAULT NULL, owner_id CHAR(36) DEFAULT NULL, composing_since DATETIME DEFAULT NULL, shared_mailbox_id CHAR(36) DEFAULT NULL, taken_by_id CHAR(36) DEFAULT NULL, composing_by_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_2EBCCA8F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2EBCCA8F12FFD503 FOREIGN KEY (shared_mailbox_id) REFERENCES shared_mailbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2EBCCA8F17F014F6 FOREIGN KEY (taken_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2EBCCA8F7B3EA35 FOREIGN KEY (composing_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suivi (id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id) SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id FROM __temp__suivi');
        $this->addSql('DROP TABLE __temp__suivi');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7E3C61F9 ON suivi (owner_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F12FFD503 ON suivi (shared_mailbox_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F17F014F6 ON suivi (taken_by_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7B3EA35 ON suivi (composing_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE shared_mailbox');
        $this->addSql('DROP TABLE shared_mailbox_member');
        $this->addSql('DROP TABLE suivi_note');
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail AS SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id FROM mail');
        $this->addSql('DROP TABLE mail');
        $this->addSql('CREATE TABLE mail (id CHAR(36) NOT NULL, folder VARCHAR(60) NOT NULL, uid INTEGER NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references CLOB NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses CLOB NOT NULL, subject VARCHAR(500) NOT NULL, received_at DATETIME NOT NULL, auto_submitted BOOLEAN NOT NULL, owner_id CHAR(36) NOT NULL, account_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5126AC487E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5126AC489B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO mail (id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id) SELECT id, folder, uid, message_id, in_reply_to, thread_references, from_name, from_address, to_addresses, subject, received_at, auto_submitted, owner_id, account_id FROM __temp__mail');
        $this->addSql('DROP TABLE __temp__mail');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account_id, folder, uid)');
        $this->addSql('CREATE INDEX IDX_5126AC487E3C61F9 ON mail (owner_id)');
        $this->addSql('CREATE INDEX IDX_5126AC489B6B5FBA ON mail (account_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suivi AS SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id FROM suivi');
        $this->addSql('DROP TABLE suivi');
        $this->addSql('CREATE TABLE suivi (id CHAR(36) NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INTEGER NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason CLOB DEFAULT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_2EBCCA8F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suivi (id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id) SELECT id, subject, contact, direction, status, due_at, relance_at, relance_count, no_relance, responsible, mailbox, mail_id, reply_received, suggested_by_ai, reason, owner_id FROM __temp__suivi');
        $this->addSql('DROP TABLE __temp__suivi');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7E3C61F9 ON suivi (owner_id)');
    }
}
