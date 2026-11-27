<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Créneaux en provisoire (F-74). */
final class Version20261127100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Créneaux en provisoire';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE slot_hold (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, group_id UUID NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, contact VARCHAR(200) NOT NULL, subject VARCHAR(500) NOT NULL, mail_id VARCHAR(400) NOT NULL, replied_to_mail_id VARCHAR(400) DEFAULT NULL, suivi_id UUID DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C1E3A7907E3C61F92F4A8AA7 ON slot_hold (owner_id, ends_at)');
        $this->addSql('CREATE INDEX IDX_C1E3A7907FEA59C0 ON slot_hold (suivi_id)');
        $this->addSql('CREATE INDEX IDX_C1E3A7907E3C61F9 ON slot_hold (owner_id)');
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT FK_C1E3A7907FEA59C0 FOREIGN KEY (suivi_id) REFERENCES suivi (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT FK_C1E3A7907E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE slot_hold');
    }
}
