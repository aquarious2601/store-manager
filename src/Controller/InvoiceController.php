<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\InvoiceItem;
use App\Entity\Invoice;

class InvoiceController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/invoices', name: 'app_invoice_list')]
    public function list(InvoiceRepository $invoiceRepository): Response
    {
        $invoices = $invoiceRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('invoice/list.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/{id}', name: 'app_invoice_show')]
    public function show(InvoiceRepository $invoiceRepository, $id): Response
    {
        $id = (int) $id; // Cast id to integer
        $invoice = $invoiceRepository->find($id);

        if (!$invoice) {
            throw $this->createNotFoundException('Invoice not found');
        }

        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/invoices/{id}/delete', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(InvoiceRepository $invoiceRepository, $id): Response
    {
        $entityManager = $this->entityManager;
        $invoice = $invoiceRepository->find($id);
        if (!$invoice) {
            return $this->json(['success' => false, 'error' => 'Invoice not found'], 404);
        }
        $entityManager->remove($invoice);
        $entityManager->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/search', name: 'app_invoice_search')]
    public function search(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $query = $request->query->get('query', '');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $minAmount = $request->query->get('minAmount');
        $maxAmount = $request->query->get('maxAmount');

        $qb = $invoiceRepository->createQueryBuilder('i')
            ->leftJoin('i.items', 'item')
            ->leftJoin('item.productEntity', 'product')
            ->addSelect('item')
            ->addSelect('product')
            ->orderBy('i.orderDate', 'DESC');

        if ($query) {
            $qb->andWhere('LOWER(i.invoiceNumber) LIKE LOWER(:query) OR 
                          LOWER(i.orderReference) LIKE LOWER(:query) OR 
                          LOWER(product.name) LIKE LOWER(:query)')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($startDate) {
            $qb->andWhere('i.orderDate >= :startDate')
               ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('i.orderDate <= :endDate')
               ->setParameter('endDate', new \DateTime($endDate));
        }

        if ($minAmount) {
            $qb->andWhere('i.totalAmount >= :minAmount')
               ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount) {
            $qb->andWhere('i.totalAmount <= :maxAmount')
               ->setParameter('maxAmount', $maxAmount);
        }

        $invoices = $qb->getQuery()->getResult();

        // Filter items based on search query
        $filteredInvoices = [];
        foreach ($invoices as $invoice) {
            $filteredItems = [];
            foreach ($invoice->getItems() as $item) {
                if ($query) {
                    // Check if the item matches the search query
                    if (stripos($item->getProductEntity()?->getName() ?? '', $query) !== false) {
                        $filteredItems[] = $item;
                    }
                } else {
                    $filteredItems[] = $item;
                }
            }
            
            if (!empty($filteredItems)) {
                $invoice->setFilteredItems($filteredItems);
                $filteredInvoices[] = $invoice;
            }
        }

        return $this->render('invoice/search.html.twig', [
            'invoices' => $filteredInvoices,
            'query' => $query,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'minAmount' => $minAmount,
            'maxAmount' => $maxAmount,
        ]);
    }

    #[Route('/rapport', name: 'app_invoice_rapport')]
    public function rapport(InvoiceRepository $invoiceRepository): Response
    {
        $invoices = $invoiceRepository->findAll();
        $monthlySummaries = [];
        $weeklySummaries = [];
        $productSummaries = [];

        foreach ($invoices as $invoice) {
            // Calculate total amount for this invoice
            $invoiceTotal = $invoice->calculateTotalAmount();
            
            // Group by month
            $monthKey = $invoice->getInvoiceDate()->format('Y-m');
            $monthName = $invoice->getInvoiceDate()->format('F Y');
            
            if (!isset($monthlySummaries[$monthKey])) {
                $monthlySummaries[$monthKey] = [
                    'month' => $monthName,
                    'totalInvoices' => 0,
                    'totalAmount' => 0,
                    'invoices' => []
                ];
            }
            
            $monthlySummaries[$monthKey]['totalInvoices']++;
            $monthlySummaries[$monthKey]['totalAmount'] += $invoiceTotal;
            $monthlySummaries[$monthKey]['invoices'][] = [
                'entity' => $invoice,
                'total' => $invoiceTotal
            ];

            // Group by week
            $weekKey = $invoice->getInvoiceDate()->format('Y-W');
            $weekStart = (new \DateTime())->setISODate(
                $invoice->getInvoiceDate()->format('Y'),
                $invoice->getInvoiceDate()->format('W')
            );
            $weekEnd = (clone $weekStart)->modify('+6 days');
            $weekName = sprintf('Week %s (%s - %s)',
                $invoice->getInvoiceDate()->format('W'),
                $weekStart->format('d/m/Y'),
                $weekEnd->format('d/m/Y')
            );
            
            if (!isset($weeklySummaries[$weekKey])) {
                $weeklySummaries[$weekKey] = [
                    'week' => $weekName,
                    'totalInvoices' => 0,
                    'totalAmount' => 0,
                    'invoices' => []
                ];
            }
            
            $weeklySummaries[$weekKey]['totalInvoices']++;
            $weeklySummaries[$weekKey]['totalAmount'] += $invoiceTotal;
            $weeklySummaries[$weekKey]['invoices'][] = [
                'entity' => $invoice,
                'total' => $invoiceTotal
            ];

            // Group products by month
            if (!isset($productSummaries[$monthKey])) {
                $productSummaries[$monthKey] = [
                    'month' => $monthName,
                    'products' => []
                ];
            }

            foreach ($invoice->getItems() as $item) {
                $productName = $item->getProductEntity()?->getName() ?? 'Unknown Product';
                if (!isset($productSummaries[$monthKey]['products'][$productName])) {
                    $productSummaries[$monthKey]['products'][$productName] = 0;
                }
                $productSummaries[$monthKey]['products'][$productName] += $item->getQuantity();
            }
        }

        // Sort monthly summaries by month in descending order
        krsort($monthlySummaries);
        krsort($weeklySummaries);
        krsort($productSummaries);

        // Sort invoices by order date within each month and week
        foreach ($monthlySummaries as &$summary) {
            usort($summary['invoices'], function($a, $b) {
                return $b['entity']->getOrderDate() <=> $a['entity']->getOrderDate();
            });
        }

        foreach ($weeklySummaries as &$summary) {
            usort($summary['invoices'], function($a, $b) {
                return $b['entity']->getOrderDate() <=> $a['entity']->getOrderDate();
            });
        }

        // Sort products by quantity and limit to top 10 for each month
        foreach ($productSummaries as $monthKey => &$summary) {
            arsort($summary['products']);
            $summary['products'] = array_slice($summary['products'], 0, 10, true);
        }

        return $this->render('invoice/rapport.html.twig', [
            'monthlySummaries' => $monthlySummaries,
            'weeklySummaries' => $weeklySummaries,
            'productSummaries' => $productSummaries
        ]);
    }
} 