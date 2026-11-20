<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120221725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendar_account (id CHAR(36) NOT NULL, label VARCHAR(160) NOT NULL, kind VARCHAR(10) NOT NULL, url VARCHAR(500) NOT NULL, username VARCHAR(180) NOT NULL, password_encrypted CLOB NOT NULL, personal BOOLEAN NOT NULL, last_sync_at DATETIME DEFAULT NULL, last_error CLOB DEFAULT NULL, failure_count INTEGER NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_1F412D637E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1F412D637E3C61F9 ON calendar_account (owner_id)');
        $this->addSql('CREATE TABLE calendar_event (id CHAR(36) NOT NULL, uid VARCHAR(255) NOT NULL, title VARCHAR(300) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, location VARCHAR(300) DEFAULT NULL, owner_id CHAR(36) NOT NULL, calendar_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_57FA09C97E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_57FA09C9A40A2C8 FOREIGN KEY (calendar_id) REFERENCES calendar_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_57FA09C97E3C61F955A0507C ON calendar_event (owner_id, starts_at)');
        $this->addSql('CREATE UNIQUE INDEX event_uid ON calendar_event (calendar_id, uid)');
        $this->addSql('CREATE INDEX IDX_57FA09C97E3C61F9 ON calendar_event (owner_id)');
        $this->addSql('CREATE INDEX IDX_57FA09C9A40A2C8 ON calendar_event (calendar_id)');
        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL, title VARCHAR(300) NOT NULL, description CLOB NOT NULL, due_at DATE DEFAULT NULL, priority INTEGER NOT NULL, project VARCHAR(120) DEFAULT NULL, tags CLOB NOT NULL, done BOOLEAN NOT NULL, origin VARCHAR(10) NOT NULL, to_confirm BOOLEAN NOT NULL, source VARCHAR(300) DEFAULT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_527EDB257E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_527EDB257E3C61F9 ON task (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE calendar_account');
        $this->addSql('DROP TABLE calendar_event');
        $this->addSql('DROP TABLE task');
    }
}
