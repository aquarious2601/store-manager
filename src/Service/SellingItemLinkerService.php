<?php

namespace App\Service;

use App\Entity\SellingItem;
use App\Entity\InvoiceItem;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SellingItemLinkerService
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Link a selling item to the most recent matching invoice item
     */
    public function linkSellingItem(SellingItem $sellingItem): bool
    {
        try {
            // Find the most recent matching InvoiceItem by description that isn't already linked
            $invoiceItem = $this->findMatchingInvoiceItem($sellingItem);

            if ($invoiceItem) {
                $sellingItem->setInvoiceItem($invoiceItem);
                $this->entityManager->persist($sellingItem);
                $this->logger->info(sprintf(
                    'Linked selling item %d to invoice item %d',
                    $sellingItem->getId(),
                    $invoiceItem->getId()
                ));
                return true;
            }

            $this->logger->info(sprintf(
                'No matching invoice item found for selling item %d with description: %s',
                $sellingItem->getId(),
                $sellingItem->getDescription()
            ));
            return false;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error linking selling item %d: %s',
                $sellingItem->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Link multiple selling items in batches
     */
    public function linkSellingItems(array $sellingItems, int $batchSize = 100): array
    {
        $linkedCount = 0;
        $skippedCount = 0;
        $totalProcessed = 0;

        foreach ($sellingItems as $sellingItem) {
            if ($this->linkSellingItem($sellingItem)) {
                $linkedCount++;
            } else {
                $skippedCount++;
            }
            $totalProcessed++;

            // Flush in batches
            if ($totalProcessed % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        // Final flush for remaining items
        $this->entityManager->flush();
        $this->entityManager->clear();

        return [
            'linked' => $linkedCount,
            'skipped' => $skippedCount,
            'total' => $totalProcessed
        ];
    }

    private function findMatchingInvoiceItem(SellingItem $sellingItem): ?InvoiceItem
    {
        // First try exact match
        $exactMatch = $this->entityManager->getRepository(InvoiceItem::class)
            ->createQueryBuilder('ii')
            ->leftJoin('ii.invoice', 'inv')
            ->where('ii.description = :description')
            ->setParameter('description', $sellingItem->getDescription())
            ->orderBy('inv.orderDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($exactMatch) {
            return $exactMatch;
        }

        // If no exact match found, try LIKE operation
        return $this->entityManager->getRepository(InvoiceItem::class)
            ->createQueryBuilder('ii')
            ->leftJoin('ii.invoice', 'inv')
            ->where('ii.description LIKE :description')
            ->setParameter('description', '%' . $sellingItem->getDescription() . '%')
            ->orderBy('inv.orderDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 