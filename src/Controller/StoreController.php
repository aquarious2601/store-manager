<?php

namespace App\Controller;

use App\Entity\Store;
use App\Form\StoreType;
use App\Repository\StoreRepository;
use App\Service\StoreCrawlerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/store')]
class StoreController extends AbstractController
{
    #[Route('/', name: 'app_store_index', methods: ['GET'])]
    public function index(StoreRepository $storeRepository): Response
    {
        return $this->render('store/index.html.twig', [
            'stores' => $storeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_store_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $store = new Store();
        $form = $this->createForm(StoreType::class, $store);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($store);
            $entityManager->flush();

            $this->addFlash('success', 'Store created successfully.');
            return $this->redirectToRoute('app_store_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('store/new.html.twig', [
            'store' => $store,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_store_show')]
    public function show(Store $store, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $view = $request->query->get('view', 'daily');
        $itemsPerPage = 20;

        // Get all sellings for this store
        $sellings = $store->getSellings()->toArray();

        // Calculate price differences for each selling item
        foreach ($sellings as $selling) {
            foreach ($selling->getItems() as $item) {
                if ($item->getInvoiceItem()) {
                    $sellingUnitPrice = (float) $item->getUnitPrice();
                    $invoiceUnitPrice = (float) $item->getInvoiceItem()->getUnitPrice();
                    
                    // Calculate absolute difference
                    $difference = $sellingUnitPrice - $invoiceUnitPrice;
                    
                    // Calculate percentage difference relative to invoice price
                    $percentageDifference = $sellingUnitPrice != 0 ? ($difference / $sellingUnitPrice * 100) : 0;
                    
                    // Store the calculations in the item object
                    $item->setPriceDifference($difference);
                    $item->setPriceDifferencePercentage($percentageDifference);

                    // Debug logging
                    error_log(sprintf(
                        "Item %d: Selling Price: %.2f, Invoice Price: %.2f, Difference: %.2f, Percentage: %.2f%%",
                        $item->getId(),
                        $sellingUnitPrice,
                        $invoiceUnitPrice,
                        $difference,
                        $percentageDifference
                    ));
                } else {
                    // Reset values if no invoice item is linked
                    $item->setPriceDifference(null);
                    $item->setPriceDifferencePercentage(null);
                    
                    // Debug logging
                    error_log(sprintf("Item %d: No invoice item linked", $item->getId()));
                }
            }
        }

        // Group sellings based on view type
        $groupedSellings = [];
        foreach ($sellings as $selling) {
            $date = $selling->getDate();
            
            switch ($view) {
                case 'weekly':
                    // Get the Monday of the week
                    $weekStart = clone $date;
                    $weekStart->modify('monday this week');
                    $key = $weekStart->format('Y-m-d');
                    break;
                case 'monthly':
                    // Get the first day of the month
                    $key = $date->format('Y-m');
                    break;
                default: // daily
                    $key = $date->format('Y-m-d');
            }

            if (!isset($groupedSellings[$key])) {
                $groupedSellings[$key] = [];
            }
            $groupedSellings[$key][] = $selling;
        }

        // Sort dates in descending order
        krsort($groupedSellings);

        // Calculate pagination
        $totalItems = count($groupedSellings);
        $totalPages = ceil($totalItems / $itemsPerPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $itemsPerPage;

        // Get the dates for the current page
        $dates = array_keys($groupedSellings);
        $paginatedDates = array_slice($dates, $offset, $itemsPerPage);

        // Create paginated data
        $currentPageSellings = [];
        foreach ($paginatedDates as $date) {
            $currentPageSellings[$date] = $groupedSellings[$date];
        }

        // Calculate totals
        $totalInvoices = count($sellings);
        $totalHT = 0;
        $totalTTC = 0;
        foreach ($sellings as $selling) {
            $totalHT += (float) $selling->getAmountHT();
            $totalTTC += (float) $selling->getAmountTTC();
        }
        
        return $this->render('store/show.html.twig', [
            'store' => $store,
            'grouped_sellings' => $currentPageSellings,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'current_view' => $view,
            'totalInvoices' => $totalInvoices,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_store_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Store $store, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StoreType::class, $store);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Store updated successfully.');
            return $this->redirectToRoute('app_store_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('store/edit.html.twig', [
            'store' => $store,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/crawl', name: 'app_store_crawl', methods: ['POST'])]
    public function crawl(Store $store, StoreCrawlerService $storeCrawlerService): JsonResponse
    {
        $result = $storeCrawlerService->crawlStore($store, 100);
        
        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => sprintf('Successfully crawled %d invoices', count($result['created_sellings']))
            ]);
        }
        
        return $this->json([
            'success' => false,
            'error' => $result['error']
        ], 500);
    }

    #[Route('/{id}', name: 'app_store_delete', methods: ['POST'])]
    public function delete(Request $request, Store $store, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$store->getId(), $request->request->get('_token'))) {
            $entityManager->remove($store);
            $entityManager->flush();
            $this->addFlash('success', 'Store deleted successfully.');
        }

        return $this->redirectToRoute('app_store_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/sellings/{date}', name: 'app_store_sellings', methods: ['GET'])]
    public function getSellings(Store $store, string $date, Request $request): JsonResponse
    {
        $view = $request->query->get('view', 'daily');
        $sellings = $store->getSellings();

        // Filter sellings for the specific date
        $filteredSellings = [];
        foreach ($sellings as $selling) {
            $sellingDate = $selling->getDate();
            $match = false;

            switch ($view) {
                case 'weekly':
                    // Get the Monday of the week
                    $weekStart = clone $sellingDate;
                    $weekStart->modify('monday this week');
                    $match = $weekStart->format('Y-m-d') === $date;
                    break;
                case 'monthly':
                    $match = $sellingDate->format('Y-m') === $date;
                    break;
                default: // daily
                    $match = $sellingDate->format('Y-m-d') === $date;
            }

            if ($match) {
                $filteredSellings[] = $selling;
            }
        }

        // Calculate totals
        $totalHT = 0;
        $totalTTC = 0;
        foreach ($filteredSellings as $selling) {
            $totalHT += (float) $selling->getAmountHT();
            $totalTTC += (float) $selling->getAmountTTC();
        }

        // Prepare items summary
        $itemsSummary = [];
        foreach ($filteredSellings as $selling) {
            foreach ($selling->getItems() as $item) {
                if (!isset($itemsSummary[$item->getDescription()])) {
                    $itemsSummary[$item->getDescription()] = [
                        'quantity' => 0,
                        'amount' => 0
                    ];
                }
                $itemsSummary[$item->getDescription()]['quantity'] += $item->getQuantity();
                $itemsSummary[$item->getDescription()]['amount'] += $item->getTotal();
            }
        }

        // Sort items by quantity
        uasort($itemsSummary, function($a, $b) {
            return $b['quantity'] <=> $a['quantity'];
        });

        // Sort sellings by ID in descending order
        usort($filteredSellings, function($a, $b) {
            return $b->getId() <=> $a->getId();
        });

        return $this->json([
            'success' => true,
            'data' => [
                'sellings' => array_map(function($selling) {
                    return [
                        'id' => $selling->getId(),
                        'invoiceNumber' => $selling->getInvoiceNumber(),
                        'paymentMethod' => $selling->getPaymentMethod(),
                        'amountHT' => $selling->getAmountHT(),
                        'amountTTC' => $selling->getAmountTTC(),
                        'status' => $selling->getStatus(),
                        'items' => array_map(function($item) {
                            $invoiceItem = $item->getInvoiceItem();
                            $priceDifference = null;
                            $priceDifferencePercentage = null;

                            if ($invoiceItem) {
                                $priceDifference = $item->getUnitPrice() - $invoiceItem->getUnitPrice();
                                if ($item->getUnitPrice() > 0) {
                                    $priceDifferencePercentage = ($priceDifference / $item->getUnitPrice()) * 100;
                                }
                            }

                            return [
                                'description' => $item->getDescription(),
                                'quantity' => $item->getQuantity(),
                                'unitPrice' => $item->getUnitPrice(),
                                'taxRate' => $item->getTaxRate(),
                                'total' => $item->getTotal(),
                                'invoiceItem' => $invoiceItem ? [
                                    'unitPrice' => $invoiceItem->getUnitPrice(),
                                ] : null,
                                'priceDifference' => $priceDifference,
                                'priceDifferencePercentage' => $priceDifferencePercentage,
                            ];
                        }, $selling->getItems()->toArray()),
                    ];
                }, $filteredSellings),
                'summary' => [
                    'totalInvoices' => count($filteredSellings),
                    'totalHT' => $totalHT,
                    'totalTTC' => $totalTTC,
                    'items' => $itemsSummary,
                ],
            ],
        ]);
    }
} 