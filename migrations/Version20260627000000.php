<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add (project_id, received_at) index on log_entry for freshness query';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_log_entry_project_received_at ON log_entry (project_id, received_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_log_entry_project_received_at ON log_entry');
    }
}
