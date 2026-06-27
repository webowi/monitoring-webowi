<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add key_value (plaintext) column to ingestion_key for S-04 reveal endpoint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingestion_key ADD key_value VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingestion_key DROP COLUMN key_value');
    }
}
