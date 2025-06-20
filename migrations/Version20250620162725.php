<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620162725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD product_entity_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DEF85CBD0 FOREIGN KEY (product_entity_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FDCE288DEF85CBD0 ON selling_item (product_entity_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DEF85CBD0
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_FDCE288DEF85CBD0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE selling_item DROP product_entity_id
        SQL);
    }
}
