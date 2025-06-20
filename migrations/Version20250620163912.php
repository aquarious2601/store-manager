<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620163912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP CONSTRAINT fk_fdce288de0b6648d
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_fdce288de0b6648d
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP invoice_item_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP description
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD invoice_item_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD description TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD CONSTRAINT fk_fdce288de0b6648d FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_fdce288de0b6648d ON selling_item (invoice_item_id)
        SQL);
    }
}
