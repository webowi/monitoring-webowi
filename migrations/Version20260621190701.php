<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621190701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE log_entry (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, occurred_at DATETIME NOT NULL, received_at DATETIME NOT NULL, severity VARCHAR(32) NOT NULL, message LONGTEXT NOT NULL, http_status_code SMALLINT DEFAULT NULL, exception_class VARCHAR(255) DEFAULT NULL, context JSON NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_B5F762DD17F50A6 (uuid), INDEX idx_log_entry_project_occurred_at (project_id, occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE log_entry');
    }
}
