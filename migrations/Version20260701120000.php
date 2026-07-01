<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphaned organization.is_verified column (no longer mapped on the Organization entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP COLUMN is_verified');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD is_verified TINYINT NOT NULL');
    }
}
