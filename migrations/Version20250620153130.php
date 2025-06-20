<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620153130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item DROP description
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item DROP reference
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item DROP code_eans
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item DROP product
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item ADD description TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item ADD reference VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item ADD code_eans VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invoice_item ADD product TEXT DEFAULT NULL
        SQL);
    }
}
