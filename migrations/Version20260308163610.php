<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308163610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration to create the database schema for the application, including tables for users, organizations, projects, ingestion keys, and password tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ingestion_key (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(191) NOT NULL, key_hash VARCHAR(128) NOT NULL, status VARCHAR(32) DEFAULT \'active\' NOT NULL, revoked_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, project_id INT NOT NULL, UNIQUE INDEX UNIQ_CF55150DD17F50A6 (uuid), UNIQUE INDEX UNIQ_CF55150D57BFB971 (key_hash), INDEX IDX_CF55150D166D1F9C (project_id), INDEX idx_ingestion_key_hash (key_hash), INDEX idx_ingestion_key_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(191) DEFAULT NULL, slug VARCHAR(191) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, logo_size INT DEFAULT NULL, is_verified TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_C1EE637CD17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE password_token (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, token VARCHAR(255) DEFAULT NULL, expired_at DATETIME DEFAULT NULL, activated_at DATETIME DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_BEAB6C24D17F50A6 (uuid), INDEX IDX_BEAB6C24A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(500) NOT NULL, status VARCHAR(191) DEFAULT \'active\' NOT NULL, platform VARCHAR(50) DEFAULT \'symfony\' NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, organization_id INT NOT NULL, UNIQUE INDEX UNIQ_2FB3D0EED17F50A6 (uuid), UNIQUE INDEX UNIQ_2FB3D0EE5E237E06 (name), INDEX IDX_2FB3D0EE32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(191) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, avatar_size INT DEFAULT NULL, is_verified TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, secret VARCHAR(255) DEFAULT NULL, organization_id INT NOT NULL, UNIQUE INDEX UNIQ_8D93D649D17F50A6 (uuid), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D64932C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ingestion_key ADD CONSTRAINT FK_CF55150D166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_token ADD CONSTRAINT FK_BEAB6C24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingestion_key DROP FOREIGN KEY FK_CF55150D166D1F9C');
        $this->addSql('ALTER TABLE password_token DROP FOREIGN KEY FK_BEAB6C24A76ED395');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE32C8A3DE');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64932C8A3DE');
        $this->addSql('DROP TABLE ingestion_key');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE password_token');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE user');
    }
}
