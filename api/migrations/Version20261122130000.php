<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Signature HTML des identités et limite de pièces jointes par compte. */
final class Version20261122130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signature HTML, limite de pièces jointes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity ADD signature_html TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mail_account ADD attachment_limit_mb INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity DROP signature_html');
        $this->addSql('ALTER TABLE mail_account DROP attachment_limit_mb');
    }
}
