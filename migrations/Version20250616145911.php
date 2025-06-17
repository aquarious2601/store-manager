<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616145911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD invoice_item_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DE0B6648D FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_FDCE288DE0B6648D ON selling_item (invoice_item_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DE0B6648D
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_FDCE288DE0B6648D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP invoice_item_id
        SQL);
    }
}
