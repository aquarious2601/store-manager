<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616152103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop and recreate invoice_item_id column without unique constraint';
    }

    public function up(Schema $schema): void
    {
        // Drop the column
        $this->addSql('ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DE0B6648D');
        $this->addSql('ALTER TABLE selling_item DROP COLUMN invoice_item_id');
        
        // Recreate the column without unique constraint
        $this->addSql('ALTER TABLE selling_item ADD invoice_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DE0B6648D FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FDCE288DE0B6648D ON selling_item (invoice_item_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop the column
        $this->addSql('ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DE0B6648D');
        $this->addSql('ALTER TABLE selling_item DROP COLUMN invoice_item_id');
        
        // Recreate the column with unique constraint
        $this->addSql('ALTER TABLE selling_item ADD invoice_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DE0B6648D FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FDCE288DE0B6648D ON selling_item (invoice_item_id)');
    }
}
