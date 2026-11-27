<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Journal IA : noter si un modèle de langage a répondu (indicateurs d'usage). */
final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal IA : réponse d’un modèle ou non';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_call ADD by_model BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_call DROP by_model');
    }
}
