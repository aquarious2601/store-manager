<?php

namespace App\Service;

use App\Entity\SellingItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class PriceComparisonService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get price comparison data for a selling item
     */
    public function getPriceComparison(SellingItem $sellingItem): array
    {
        $product = $sellingItem->getProductEntity();
        $priceDifference = null;
        $priceDifferencePercentage = null;
        $latestInvoiceItem = null;

        if ($product) {
            $latestInvoiceItem = $this->findLatestInvoiceItemForProduct($product);

            if ($latestInvoiceItem) {
                $priceDifference = (float)$sellingItem->getUnitPrice() - (float)$latestInvoiceItem->getUnitPrice();
                if ((float)$sellingItem->getUnitPrice() > 0) {
                    $priceDifferencePercentage = ($priceDifference / (float)$sellingItem->getUnitPrice()) * 100;
                }
            }
        }

        return [
            'latestInvoiceItem' => $latestInvoiceItem ? [
                'unitPrice' => $latestInvoiceItem->getUnitPrice(),
                'invoiceNumber' => $latestInvoiceItem->getInvoice()->getInvoiceNumber(),
                'orderDate' => $latestInvoiceItem->getInvoice()->getOrderDate()->format('Y-m-d'),
            ] : null,
            'priceDifference' => $priceDifference,
            'priceDifferencePercentage' => $priceDifferencePercentage,
        ];
    }

    /**
     * Get price comparison data for multiple selling items (optimized)
     */
    public function getPriceComparisonsForItems(array $sellingItems): array
    {
        $results = [];
        $productIds = [];

        // Collect all product IDs
        foreach ($sellingItems as $sellingItem) {
            $product = $sellingItem->getProductEntity();
            if ($product) {
                $productIds[] = $product->getId();
            }
        }

        // Get latest invoice items for all products in one query
        $latestInvoiceItems = [];
        if (!empty($productIds)) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('ii, inv')
               ->from(\App\Entity\InvoiceItem::class, 'ii')
               ->leftJoin('ii.invoice', 'inv')
               ->where('ii.productEntity IN (:productIds)')
               ->setParameter('productIds', $productIds)
               ->orderBy('inv.orderDate', 'DESC');

            $allInvoiceItems = $qb->getQuery()->getResult();

            // Group by product and take the latest for each
            foreach ($allInvoiceItems as $invoiceItem) {
                $productId = $invoiceItem->getProductEntity()->getId();
                if (!isset($latestInvoiceItems[$productId])) {
                    $latestInvoiceItems[$productId] = $invoiceItem;
                }
            }
        }

        // Calculate price differences for each selling item
        foreach ($sellingItems as $sellingItem) {
            $product = $sellingItem->getProductEntity();
            $priceDifference = null;
            $priceDifferencePercentage = null;
            $latestInvoiceItem = null;

            if ($product && isset($latestInvoiceItems[$product->getId()])) {
                $latestInvoiceItem = $latestInvoiceItems[$product->getId()];
                $priceDifference = (float)$sellingItem->getUnitPrice() - (float)$latestInvoiceItem->getUnitPrice();
                if ((float)$sellingItem->getUnitPrice() > 0) {
                    $priceDifferencePercentage = ($priceDifference / (float)$sellingItem->getUnitPrice()) * 100;
                }
            }

            $results[$sellingItem->getId()] = [
                'latestInvoiceItem' => $latestInvoiceItem ? [
                    'unitPrice' => $latestInvoiceItem->getUnitPrice(),
                    'invoiceNumber' => $latestInvoiceItem->getInvoice()->getInvoiceNumber(),
                    'orderDate' => $latestInvoiceItem->getInvoice()->getOrderDate()->format('Y-m-d'),
                ] : null,
                'priceDifference' => $priceDifference,
                'priceDifferencePercentage' => $priceDifferencePercentage,
            ];
        }

        return $results;
    }

    /**
     * Find the latest invoice item for a product
     */
    private function findLatestInvoiceItemForProduct(Product $product): ?\App\Entity\InvoiceItem
    {
        return $this->entityManager->getRepository(\App\Entity\InvoiceItem::class)
            ->createQueryBuilder('ii')
            ->leftJoin('ii.invoice', 'inv')
            ->where('ii.productEntity = :product')
            ->setParameter('product', $product)
            ->orderBy('inv.orderDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 