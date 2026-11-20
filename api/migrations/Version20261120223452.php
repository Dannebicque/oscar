<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120223452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_call (id UUID NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, provider VARCHAR(60) NOT NULL, purpose VARCHAR(40) NOT NULL, input_summary VARCHAR(300) NOT NULL, output JSON NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C8DF6E857E3C61F96A57FD3C ON ai_call (owner_id, at)');
        $this->addSql('CREATE INDEX IDX_C8DF6E857E3C61F9 ON ai_call (owner_id)');
        $this->addSql('CREATE TABLE api_token (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, label VARCHAR(80) NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA ON api_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBA76ED395 ON api_token (user_id)');
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, username VARCHAR(120) NOT NULL, display_name VARCHAR(160) NOT NULL, email VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE TABLE calendar_account (id UUID NOT NULL, label VARCHAR(160) NOT NULL, kind VARCHAR(10) NOT NULL, url VARCHAR(500) NOT NULL, username VARCHAR(180) NOT NULL, password_encrypted TEXT NOT NULL, personal BOOLEAN NOT NULL, last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_error TEXT DEFAULT NULL, failure_count INT NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1F412D637E3C61F9 ON calendar_account (owner_id)');
        $this->addSql('CREATE TABLE calendar_event (id UUID NOT NULL, uid VARCHAR(255) NOT NULL, title VARCHAR(300) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, location VARCHAR(300) DEFAULT NULL, owner_id UUID NOT NULL, calendar_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_57FA09C97E3C61F955A0507C ON calendar_event (owner_id, starts_at)');
        $this->addSql('CREATE UNIQUE INDEX event_uid ON calendar_event (calendar_id, uid)');
        $this->addSql('CREATE INDEX IDX_57FA09C97E3C61F9 ON calendar_event (owner_id)');
        $this->addSql('CREATE INDEX IDX_57FA09C9A40A2C8 ON calendar_event (calendar_id)');
        $this->addSql('CREATE TABLE journal_entry (id UUID NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, author VARCHAR(160) NOT NULL, action VARCHAR(120) NOT NULL, suivi_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C8FAAE5A7FEA59C06A57FD3C ON journal_entry (suivi_id, at)');
        $this->addSql('CREATE INDEX IDX_C8FAAE5A7FEA59C0 ON journal_entry (suivi_id)');
        $this->addSql('CREATE TABLE log_entry (id UUID NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account VARCHAR(160) NOT NULL, type VARCHAR(30) NOT NULL, message TEXT NOT NULL, action VARCHAR(30) NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B5F762D7E3C61F96A57FD3C ON log_entry (owner_id, at)');
        $this->addSql('CREATE INDEX IDX_B5F762D7E3C61F9 ON log_entry (owner_id)');
        $this->addSql('CREATE TABLE mail (id UUID NOT NULL, folder VARCHAR(60) NOT NULL, uid INT NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references JSON NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses JSON NOT NULL, subject VARCHAR(500) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, auto_submitted BOOLEAN NOT NULL, owner_id UUID NOT NULL, shared_mailbox_id UUID DEFAULT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account_id, folder, uid)');
        $this->addSql('CREATE INDEX IDX_5126AC487E3C61F9 ON mail (owner_id)');
        $this->addSql('CREATE INDEX IDX_5126AC4812FFD503 ON mail (shared_mailbox_id)');
        $this->addSql('CREATE INDEX IDX_5126AC489B6B5FBA ON mail (account_id)');
        $this->addSql('CREATE TABLE mail_account (id UUID NOT NULL, label VARCHAR(160) NOT NULL, address VARCHAR(180) NOT NULL, imap_host VARCHAR(255) NOT NULL, imap_port INT NOT NULL, imap_encryption VARCHAR(10) NOT NULL, smtp_host VARCHAR(255) NOT NULL, smtp_port INT NOT NULL, smtp_encryption VARCHAR(10) NOT NULL, username VARCHAR(180) NOT NULL, password_encrypted TEXT NOT NULL, ai_allowed BOOLEAN NOT NULL, last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_error TEXT DEFAULT NULL, failure_count INT NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A78BD7CB7E3C61F9 ON mail_account (owner_id)');
        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, read BOOLEAN NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, kind VARCHAR(30) NOT NULL, params JSON NOT NULL, target VARCHAR(30) NOT NULL, dedupe_key VARCHAR(200) NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX notification_dedupe ON notification (owner_id, dedupe_key)');
        $this->addSql('CREATE INDEX IDX_BF5476CA7E3C61F9 ON notification (owner_id)');
        $this->addSql('CREATE TABLE recap_log (id UUID NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, slot VARCHAR(10) NOT NULL, day VARCHAR(10) NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX recap_once ON recap_log (owner_id, slot, day)');
        $this->addSql('CREATE INDEX IDX_C36395497E3C61F9 ON recap_log (owner_id)');
        $this->addSql('CREATE TABLE shared_mailbox (id UUID NOT NULL, address VARCHAR(180) NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(60) NOT NULL, auto_follow BOOLEAN NOT NULL, ai_enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8748EEBCD4E6F81 ON shared_mailbox (address)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8748EEBC989D9B62 ON shared_mailbox (slug)');
        $this->addSql('CREATE TABLE shared_mailbox_member (id UUID NOT NULL, role VARCHAR(10) NOT NULL, mailbox_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX shared_member ON shared_mailbox_member (mailbox_id, user_id)');
        $this->addSql('CREATE INDEX IDX_5A41DFD766EC35CC ON shared_mailbox_member (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_5A41DFD7A76ED395 ON shared_mailbox_member (user_id)');
        $this->addSql('CREATE TABLE suivi (composing_since TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INT NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason TEXT DEFAULT NULL, owner_id UUID DEFAULT NULL, shared_mailbox_id UUID DEFAULT NULL, taken_by_id UUID DEFAULT NULL, composing_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7E3C61F9 ON suivi (owner_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F12FFD503 ON suivi (shared_mailbox_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F17F014F6 ON suivi (taken_by_id)');
        $this->addSql('CREATE INDEX IDX_2EBCCA8F7B3EA35 ON suivi (composing_by_id)');
        $this->addSql('CREATE TABLE suivi_note (id UUID NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, author VARCHAR(160) NOT NULL, text TEXT NOT NULL, suivi_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7F9DAD767FEA59C0 ON suivi_note (suivi_id)');
        $this->addSql('CREATE TABLE task (id UUID NOT NULL, title VARCHAR(300) NOT NULL, description TEXT NOT NULL, due_at DATE DEFAULT NULL, priority INT NOT NULL, project VARCHAR(120) DEFAULT NULL, tags JSON NOT NULL, done BOOLEAN NOT NULL, origin VARCHAR(10) NOT NULL, to_confirm BOOLEAN NOT NULL, source VARCHAR(300) DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_527EDB257E3C61F9 ON task (owner_id)');
        $this->addSql('CREATE TABLE user_settings (id UUID NOT NULL, setting_values JSON NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C844C57E3C61F9 ON user_settings (owner_id)');
        $this->addSql('ALTER TABLE ai_call ADD CONSTRAINT FK_C8DF6E857E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE calendar_account ADD CONSTRAINT FK_1F412D637E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C97E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9A40A2C8 FOREIGN KEY (calendar_id) REFERENCES calendar_account (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE journal_entry ADD CONSTRAINT FK_C8FAAE5A7FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE log_entry ADD CONSTRAINT FK_B5F762D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mail ADD CONSTRAINT FK_5126AC487E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mail ADD CONSTRAINT FK_5126AC4812FFD503 FOREIGN KEY (shared_mailbox_id) REFERENCES shared_mailbox (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mail ADD CONSTRAINT FK_5126AC489B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mail_account ADD CONSTRAINT FK_A78BD7CB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recap_log ADD CONSTRAINT FK_C36395497E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shared_mailbox_member ADD CONSTRAINT FK_5A41DFD766EC35CC FOREIGN KEY (mailbox_id) REFERENCES shared_mailbox (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shared_mailbox_member ADD CONSTRAINT FK_5A41DFD7A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_2EBCCA8F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_2EBCCA8F12FFD503 FOREIGN KEY (shared_mailbox_id) REFERENCES shared_mailbox (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_2EBCCA8F17F014F6 FOREIGN KEY (taken_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_2EBCCA8F7B3EA35 FOREIGN KEY (composing_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suivi_note ADD CONSTRAINT FK_7F9DAD767FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB257E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT FK_5C844C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_call DROP CONSTRAINT FK_C8DF6E857E3C61F9');
        $this->addSql('ALTER TABLE api_token DROP CONSTRAINT FK_7BA2F5EBA76ED395');
        $this->addSql('ALTER TABLE calendar_account DROP CONSTRAINT FK_1F412D637E3C61F9');
        $this->addSql('ALTER TABLE calendar_event DROP CONSTRAINT FK_57FA09C97E3C61F9');
        $this->addSql('ALTER TABLE calendar_event DROP CONSTRAINT FK_57FA09C9A40A2C8');
        $this->addSql('ALTER TABLE journal_entry DROP CONSTRAINT FK_C8FAAE5A7FEA59C0');
        $this->addSql('ALTER TABLE log_entry DROP CONSTRAINT FK_B5F762D7E3C61F9');
        $this->addSql('ALTER TABLE mail DROP CONSTRAINT FK_5126AC487E3C61F9');
        $this->addSql('ALTER TABLE mail DROP CONSTRAINT FK_5126AC4812FFD503');
        $this->addSql('ALTER TABLE mail DROP CONSTRAINT FK_5126AC489B6B5FBA');
        $this->addSql('ALTER TABLE mail_account DROP CONSTRAINT FK_A78BD7CB7E3C61F9');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA7E3C61F9');
        $this->addSql('ALTER TABLE recap_log DROP CONSTRAINT FK_C36395497E3C61F9');
        $this->addSql('ALTER TABLE shared_mailbox_member DROP CONSTRAINT FK_5A41DFD766EC35CC');
        $this->addSql('ALTER TABLE shared_mailbox_member DROP CONSTRAINT FK_5A41DFD7A76ED395');
        $this->addSql('ALTER TABLE suivi DROP CONSTRAINT FK_2EBCCA8F7E3C61F9');
        $this->addSql('ALTER TABLE suivi DROP CONSTRAINT FK_2EBCCA8F12FFD503');
        $this->addSql('ALTER TABLE suivi DROP CONSTRAINT FK_2EBCCA8F17F014F6');
        $this->addSql('ALTER TABLE suivi DROP CONSTRAINT FK_2EBCCA8F7B3EA35');
        $this->addSql('ALTER TABLE suivi_note DROP CONSTRAINT FK_7F9DAD767FEA59C0');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB257E3C61F9');
        $this->addSql('ALTER TABLE user_settings DROP CONSTRAINT FK_5C844C57E3C61F9');
        $this->addSql('DROP TABLE ai_call');
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE calendar_account');
        $this->addSql('DROP TABLE calendar_event');
        $this->addSql('DROP TABLE journal_entry');
        $this->addSql('DROP TABLE log_entry');
        $this->addSql('DROP TABLE mail');
        $this->addSql('DROP TABLE mail_account');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE recap_log');
        $this->addSql('DROP TABLE shared_mailbox');
        $this->addSql('DROP TABLE shared_mailbox_member');
        $this->addSql('DROP TABLE suivi');
        $this->addSql('DROP TABLE suivi_note');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE user_settings');
    }
}
