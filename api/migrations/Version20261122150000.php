<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Nom d'expéditeur et signature d'équipe des boîtes partagées. */
final class Version20261122150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identité d\'envoi des boîtes partagées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_mailbox ADD sender_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE shared_mailbox ADD signature TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_mailbox DROP sender_name');
        $this->addSql('ALTER TABLE shared_mailbox DROP signature');
    }
}
