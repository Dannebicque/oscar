<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120201843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE suivi (id CHAR(36) NOT NULL, subject VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, direction VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATE NOT NULL, relance_at DATE DEFAULT NULL, relance_count INTEGER NOT NULL, no_relance BOOLEAN NOT NULL, responsible VARCHAR(120) DEFAULT NULL, mailbox VARCHAR(60) NOT NULL, mail_id VARCHAR(255) DEFAULT NULL, reply_received BOOLEAN NOT NULL, suggested_by_ai BOOLEAN NOT NULL, reason CLOB DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE suivi');
    }
}
