<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618131418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add columns allowing NULL values
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD first_name VARCHAR(255) NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD last_name VARCHAR(255) NULL
        SQL);

        // Update existing rows with default values
        $this->addSql(<<<'SQL'
            UPDATE "user" SET first_name = 'Default', last_name = 'User' WHERE first_name IS NULL OR last_name IS NULL
        SQL);

        // Alter columns to NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER COLUMN first_name SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER COLUMN last_name SET NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP first_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP last_name
        SQL);
    }
}
