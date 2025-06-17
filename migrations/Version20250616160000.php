<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250616160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update relationship between InvoiceItem and SellingItem to ManyToOne';
    }

    public function up(Schema $schema): void
    {
        // Drop the existing foreign key and unique constraint
        $this->addSql('ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DE0B6648D');
        $this->addSql('DROP INDEX UNIQ_FDCE288DE0B6648D');
        
        // Recreate the foreign key without unique constraint
        $this->addSql('ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DE0B6648D FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FDCE288DE0B6648D ON selling_item (invoice_item_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop the foreign key and index
        $this->addSql('ALTER TABLE selling_item DROP CONSTRAINT FK_FDCE288DE0B6648D');
        $this->addSql('DROP INDEX IDX_FDCE288DE0B6648D');
        
        // Recreate the foreign key with unique constraint
        $this->addSql('ALTER TABLE selling_item ADD CONSTRAINT FK_FDCE288DE0B6648D FOREIGN KEY (invoice_item_id) REFERENCES invoice_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FDCE288DE0B6648D ON selling_item (invoice_item_id)');
    }
} 