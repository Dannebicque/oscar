<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Administrateur d'instance et configuration globale. */
final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Administrateur d\'instance, réglages globaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD instance_admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE TABLE instance_setting (setting_key VARCHAR(60) NOT NULL, setting_value JSON NOT NULL, PRIMARY KEY (setting_key))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE instance_setting');
        $this->addSql('ALTER TABLE app_user DROP instance_admin');
    }
}
