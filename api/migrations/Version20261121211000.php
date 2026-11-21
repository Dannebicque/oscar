<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Participants d'un événement (rappel avant rendez-vous). */
final class Version20261121211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Participants des événements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE calendar_event ADD attendees JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_event DROP attendees');
    }
}
