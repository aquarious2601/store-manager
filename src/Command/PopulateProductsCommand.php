<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\InvoiceItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-products',
    description: 'Link existing InvoiceItems to Products',
)]
class PopulateProductsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Linking InvoiceItems to Products');

        // Get all invoice items that don't have a product entity linked
        $invoiceItems = $this->entityManager->getRepository(InvoiceItem::class)
            ->createQueryBuilder('ii')
            ->where('ii.productEntity IS NULL')
            ->getQuery()
            ->getResult();
        
        $io->text(sprintf('Found %d invoice items without product links', count($invoiceItems)));

        if (empty($invoiceItems)) {
            $io->success('All invoice items are already linked to products!');
            return Command::SUCCESS;
        }

        // Get all existing products
        $existingProducts = $this->entityManager->getRepository(Product::class)->findAll();
        $productsMap = [];
        
        foreach ($existingProducts as $product) {
            $productsMap[$product->getKcCode()] = $product;
        }
        
        $io->text(sprintf('Found %d existing products', count($existingProducts)));

        // Link InvoiceItems to their corresponding Products
        $io->text('Linking InvoiceItems to Products...');
        
        $linkedCount = 0;
        $notFoundCount = 0;
        
        foreach ($invoiceItems as $invoiceItem) {
            // Since we removed the reference field, we need to find products by name
            // This is a fallback for items that weren't properly linked
            $productName = $invoiceItem->getProductEntity()?->getName() ?? 'Unknown Product';
            
            // Try to find product by name (this is not ideal but works as fallback)
            $product = null;
            foreach ($existingProducts as $existingProduct) {
                if ($existingProduct->getName() === $productName) {
                    $product = $existingProduct;
                    break;
                }
            }
            
            if ($product) {
                $invoiceItem->setProductEntity($product);
                $linkedCount++;
            } else {
                $notFoundCount++;
                $io->warning(sprintf('No product found for item with name: %s', $productName));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Linked %d invoice items to products', $linkedCount));
        if ($notFoundCount > 0) {
            $io->warning(sprintf('%d invoice items could not be linked (no matching product found)', $notFoundCount));
        }

        return Command::SUCCESS;
    }
}
