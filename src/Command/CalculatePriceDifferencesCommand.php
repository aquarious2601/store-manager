<?php

namespace App\Command;

use App\Entity\InvoiceItem;
use App\Entity\SellingItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calculate-price-differences',
    description: 'Calculate price differences between SellingItem and InvoiceItem'
)]
class CalculatePriceDifferencesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Calculating Price Differences');

        $batchSize = 100;
        $offset = 0;
        $totalProcessed = 0;
        $totalItems = 0;

        // Count total SellingItems with linked InvoiceItems
        $totalItems = $this->entityManager->getRepository(SellingItem::class)
            ->createQueryBuilder('si')
            ->select('COUNT(si.id)')
            ->where('si.invoiceItem IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $io->writeln(sprintf('Found %d SellingItems with linked InvoiceItems', $totalItems));

        // Create table headers
        $io->table(
            ['SellingItem ID', 'Description', 'Unit Sale Price', 'Invoice Amount HT', 'Difference', 'Difference %'],
            []
        );

        while (true) {
            $sellingItems = $this->entityManager->getRepository(SellingItem::class)
                ->createQueryBuilder('si')
                ->leftJoin('si.invoiceItem', 'ii')
                ->where('si.invoiceItem IS NOT NULL')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if (count($sellingItems) === 0) {
                break;
            }

            $rows = [];
            foreach ($sellingItems as $sellingItem) {
                $invoiceItem = $sellingItem->getInvoiceItem();
                if (!$invoiceItem) {
                    continue;
                }

                $unitSalePrice = $sellingItem->getUnitSalePrice();
                $amountHT = $invoiceItem->getAmountHT();

                if ($unitSalePrice === null || $amountHT === null) {
                    continue;
                }

                $difference = $unitSalePrice - $amountHT;
                $differencePercentage = $unitSalePrice !== 0 ? ($difference / $unitSalePrice) * 100 : 0;

                $rows[] = [
                    $sellingItem->getId(),
                    $sellingItem->getDescription(),
                    number_format($unitSalePrice, 2),
                    number_format($amountHT, 2),
                    number_format($difference, 2),
                    number_format($differencePercentage, 2) . '%'
                ];

                $totalProcessed++;
            }

            // Display the batch results
            $io->table(
                ['SellingItem ID', 'Description', 'Unit Sale Price', 'Invoice Amount HT', 'Difference', 'Difference %'],
                $rows
            );

            $offset += $batchSize;
            $io->writeln(sprintf('Processed %d/%d...', $totalProcessed, $totalItems));
        }

        $io->success(sprintf('Process completed: %d items analyzed', $totalProcessed));

        return Command::SUCCESS;
    }
} 