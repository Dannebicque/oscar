<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Invitation à accepter avant tout partage dans une boîte partagée. Les membres existants restent membres. */
final class Version20261126110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boîtes partagées : invitation à accepter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_mailbox_member ADD accepted BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_mailbox_member DROP accepted');
    }
}
