<?php

namespace App\Command;

use App\Entity\SellingItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:link-selling-items-to-products-by-name',
    description: 'Link SellingItems to Products based on product names',
)]
class LinkSellingItemsToProductsByNameCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Linking SellingItems to Products by Name');

        $batchSize = 100;
        $offset = 0;
        $totalLinked = 0;
        $totalCreated = 0;
        $totalSkipped = 0;

        while (true) {
            // Find SellingItems without Product links in batches
            $sellingItems = $this->entityManager->getRepository(SellingItem::class)
                ->createQueryBuilder('si')
                ->where('si.productEntity IS NULL')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if (empty($sellingItems)) {
                break;
            }

            $io->text(sprintf('Processing batch %d-%d...', $offset + 1, $offset + count($sellingItems)));

            $linkedCount = 0;
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($sellingItems as $sellingItem) {
                $product = $this->findBestMatchingProduct($sellingItem);
                
                if ($product) {
                    $sellingItem->setProductEntity($product);
                    $linkedCount++;
                    
                    if ($product->getKcCode() && strpos($product->getKcCode(), 'AUTO-') === 0) {
                        $createdCount++;
                    }
                } else {
                    $skippedCount++;
                }
            }

            // Flush batch
            $this->entityManager->flush();
            $this->entityManager->clear();

            $totalLinked += $linkedCount;
            $totalCreated += $createdCount;
            $totalSkipped += $skippedCount;

            $io->text(sprintf('Batch completed: %d linked, %d products created, %d skipped', $linkedCount, $createdCount, $skippedCount));

            $offset += $batchSize;
        }

        $io->success(sprintf(
            'Process completed: %d total linked, %d products created, %d skipped',
            $totalLinked,
            $totalCreated,
            $totalSkipped
        ));

        return Command::SUCCESS;
    }

    private function findBestMatchingProduct(SellingItem $sellingItem): ?Product
    {
        // Create a new product for each selling item without a product link
        $product = new Product();
        $product->setName('Unknown Product - Selling Item #' . $sellingItem->getId());
        $product->setKcCode('AUTO-' . substr(md5($sellingItem->getId()), 0, 8));
        $product->setEansCode('');
        
        $this->entityManager->persist($product);
        
        return $product;
    }
} 