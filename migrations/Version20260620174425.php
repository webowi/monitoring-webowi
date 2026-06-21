<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620174425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration for creating ingestion_key, organization, project, and user tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ingestion_key (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, name VARCHAR(191) NOT NULL, key_hash VARCHAR(128) NOT NULL, status VARCHAR(32) DEFAULT \'active\' NOT NULL, revoked_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_CF55150DD17F50A6 (uuid), UNIQUE INDEX UNIQ_CF55150D57BFB971 (key_hash), INDEX idx_ingestion_key_hash (key_hash), INDEX idx_ingestion_key_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(191) DEFAULT NULL, slug VARCHAR(191) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, logo_size INT DEFAULT NULL, is_verified TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_C1EE637CD17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, organization_id BINARY(16) NOT NULL, name VARCHAR(500) NOT NULL, status VARCHAR(191) DEFAULT \'active\' NOT NULL, platform VARCHAR(50) DEFAULT \'symfony\' NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_2FB3D0EED17F50A6 (uuid), UNIQUE INDEX UNIQ_2FB3D0EE5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, organization_id BINARY(16) NOT NULL, password VARCHAR(191) DEFAULT NULL, roles JSON NOT NULL, status VARCHAR(191) DEFAULT \'unverified\', created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, email VARCHAR(180) NOT NULL, secret VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649D17F50A6 (uuid), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ingestion_key');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE user');
    }
}
