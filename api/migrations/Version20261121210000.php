<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Mails programmés (F-73). */
final class Version20261121210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mails programmés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE scheduled_mail (id UUID NOT NULL, status VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, request JSON NOT NULL, send_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_SCHEDMAIL_OWNER ON scheduled_mail (owner_id)');
        $this->addSql('CREATE INDEX scheduled_mail_due ON scheduled_mail (status, send_at)');
        $this->addSql('ALTER TABLE scheduled_mail ADD CONSTRAINT FK_SCHEDMAIL_OWNER FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduled_mail');
    }
}
