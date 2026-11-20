<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120221947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recap_log (id CHAR(36) NOT NULL, sent_at DATETIME NOT NULL, slot VARCHAR(10) NOT NULL, day VARCHAR(10) NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C36395497E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX recap_once ON recap_log (owner_id, slot, day)');
        $this->addSql('CREATE INDEX IDX_C36395497E3C61F9 ON recap_log (owner_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user_settings AS SELECT id, "values", owner_id FROM user_settings');
        $this->addSql('DROP TABLE user_settings');
        $this->addSql('CREATE TABLE user_settings (id CHAR(36) NOT NULL, setting_values CLOB NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5C844C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user_settings (id, setting_values, owner_id) SELECT id, "values", owner_id FROM __temp__user_settings');
        $this->addSql('DROP TABLE __temp__user_settings');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C844C57E3C61F9 ON user_settings (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE recap_log');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user_settings AS SELECT id, setting_values, owner_id FROM user_settings');
        $this->addSql('DROP TABLE user_settings');
        $this->addSql('CREATE TABLE user_settings (id CHAR(36) NOT NULL, "values" CLOB NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5C844C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user_settings (id, "values", owner_id) SELECT id, setting_values, owner_id FROM __temp__user_settings');
        $this->addSql('DROP TABLE __temp__user_settings');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C844C57E3C61F9 ON user_settings (owner_id)');
    }
}
