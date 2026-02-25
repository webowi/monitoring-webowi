<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222130633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create company, user and password_token tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(191) DEFAULT NULL, tin VARCHAR(168) DEFAULT NULL, regon VARCHAR(168) DEFAULT NULL, province VARCHAR(191) DEFAULT NULL, street VARCHAR(191) DEFAULT NULL, zip_code VARCHAR(10) DEFAULT NULL, city VARCHAR(191) DEFAULT NULL, phone_number VARCHAR(15) DEFAULT NULL, company_email VARCHAR(191) DEFAULT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, UNIQUE INDEX UNIQ_4FBF094FD17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE password_token (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, token VARCHAR(255) DEFAULT NULL, expired_at DATETIME DEFAULT NULL, activated_at DATETIME DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_BEAB6C24D17F50A6 (uuid), INDEX IDX_BEAB6C24A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(191) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, avatar_size INT DEFAULT NULL, is_verified TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, secret VARCHAR(255) DEFAULT NULL, company_id INT NOT NULL, UNIQUE INDEX UNIQ_8D93D649D17F50A6 (uuid), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE password_token ADD CONSTRAINT FK_BEAB6C24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_token DROP FOREIGN KEY FK_BEAB6C24A76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649979B1AD6');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE password_token');
        $this->addSql('DROP TABLE user');
    }
}
