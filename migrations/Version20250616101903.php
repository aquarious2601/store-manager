<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616101903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE selling_item (id SERIAL NOT NULL, selling_id INT NOT NULL, description TEXT DEFAULT NULL, quantity NUMERIC(10, 2) NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, tax_rate VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FDCE288D155A4545 ON selling_item (selling_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288D155A4545 FOREIGN KEY (selling_id) REFERENCES selling (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288D155A4545
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE selling_item
        SQL);
    }
}
