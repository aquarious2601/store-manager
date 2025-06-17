<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250614222638 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE selling (id SERIAL NOT NULL, store_id INT NOT NULL, invoice_number VARCHAR(255) NOT NULL, date DATE NOT NULL, payment_method VARCHAR(255) NOT NULL, amount_ht NUMERIC(10, 2) NOT NULL, amount_ttc NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, details_url VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5A491BAB2DA68207 ON selling (invoice_number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5A491BABB092A811 ON selling (store_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling ADD CONSTRAINT FK_5A491BABB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling DROP CONSTRAINT FK_5A491BABB092A811
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE selling
        SQL);
    }
}
