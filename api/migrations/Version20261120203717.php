<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120203717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mail (id CHAR(36) NOT NULL, account VARCHAR(120) NOT NULL, folder VARCHAR(60) NOT NULL, uid INTEGER NOT NULL, message_id VARCHAR(255) NOT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, thread_references CLOB NOT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, to_addresses CLOB NOT NULL, subject VARCHAR(500) NOT NULL, received_at DATETIME NOT NULL, auto_submitted BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5126AC48537A1329 ON mail (message_id)');
        $this->addSql('CREATE UNIQUE INDEX mail_position ON mail (account, folder, uid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mail');
    }
}
