<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208130947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add user and password token tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE password_token (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, token VARCHAR(255) DEFAULT NULL, expired_at DATETIME DEFAULT NULL, activated_at DATETIME DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_BEAB6C24D17F50A6 (uuid), INDEX IDX_BEAB6C24A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, company_id BINARY(16) DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(191) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, avatar_size INT DEFAULT NULL, is_verified TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, created_by VARCHAR(191) DEFAULT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(191) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_by VARCHAR(191) DEFAULT NULL, secret VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649D17F50A6 (uuid), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_token');
        $this->addSql('DROP TABLE user');
    }
}
