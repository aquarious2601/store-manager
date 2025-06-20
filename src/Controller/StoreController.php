<?php

namespace App\Controller;

use App\Entity\Store;
use App\Form\StoreType;
use App\Repository\StoreRepository;
use App\Service\StoreCrawlerService;
use App\Service\PriceComparisonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/store')]
class StoreController extends AbstractController
{
    public function __construct(
        private PriceComparisonService $priceComparisonService
    ) {}

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

    #[Route('/{id}', name: 'app_store_show', methods: ['GET'])]
    public function show(Store $store, Request $request): Response
    {
        $view = $request->query->get('view', 'daily');
        $page = $request->query->getInt('page', 1);
        $perPage = 10;

        $sellings = $store->getSellings();
        $grouped_sellings = [];
        $totalHT = 0;
        $totalTTC = 0;
        $totalInvoices = count($sellings);

        // Get the date ranges
        $today = new \DateTime();
        $sevenDaysAgo = clone $today;
        $sevenDaysAgo->modify('-7 days');
        
        $fiveWeeksAgo = clone $today;
        $fiveWeeksAgo->modify('-5 weeks');

        foreach ($sellings as $selling) {
            $totalHT += $selling->getAmountHT();
            $totalTTC += $selling->getAmountTTC();
            
            $date = $selling->getDate();
            
            if ($view === 'daily') {
                $dateStr = $date->format('Y-m-d');
                // Only include sales from the last 7 days
                if ($date >= $sevenDaysAgo) {
                    if (!isset($grouped_sellings[$dateStr])) {
                        $grouped_sellings[$dateStr] = [];
                    }
                    $grouped_sellings[$dateStr][] = $selling;
                }
            } elseif ($view === 'weekly') {
                $weekStart = clone $date;
                $weekStart->modify('monday this week');
                $dateStr = $weekStart->format('Y-m-d');
                // Only include sales from the last 5 weeks
                if ($weekStart >= $fiveWeeksAgo) {
                    if (!isset($grouped_sellings[$dateStr])) {
                        $grouped_sellings[$dateStr] = [];
                    }
                    $grouped_sellings[$dateStr][] = $selling;
                }
            } else {
                $dateStr = $date->format('Y-m');
                if (!isset($grouped_sellings[$dateStr])) {
                    $grouped_sellings[$dateStr] = [];
                }
                $grouped_sellings[$dateStr][] = $selling;
            }
        }

        // Sort by date in descending order (newest first)
        krsort($grouped_sellings);

        // Calculate pagination
        $total_pages = ceil(count($grouped_sellings) / $perPage);
        $offset = ($page - 1) * $perPage;
        $grouped_sellings = array_slice($grouped_sellings, $offset, $perPage, true);

        return $this->render('store/show.html.twig', [
            'store' => $store,
            'grouped_sellings' => $grouped_sellings,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC,
            'totalInvoices' => $totalInvoices,
            'current_view' => $view,
            'current_page' => $page,
            'total_pages' => $total_pages
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

        // Calculate summary statistics
        $itemsSummary = [];
        foreach ($filteredSellings as $selling) {
            foreach ($selling->getItems() as $item) {
                $productName = $item->getProductEntity() 
                    ? $item->getProductEntity()->getName() 
                    : 'Unknown Product';
                
                if (!isset($itemsSummary[$productName])) {
                    $itemsSummary[$productName] = [
                        'quantity' => 0,
                        'amount' => 0,
                    ];
                }
                $itemsSummary[$productName]['quantity'] += $item->getQuantity();
                $itemsSummary[$productName]['amount'] += $item->getTotal();
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

        // Get all selling items for price comparison
        $allSellingItems = [];
        foreach ($filteredSellings as $selling) {
            foreach ($selling->getItems() as $item) {
                $allSellingItems[] = $item;
            }
        }

        // Get price comparisons for all items at once
        $priceComparisons = $this->priceComparisonService->getPriceComparisonsForItems($allSellingItems);

        return $this->json([
            'success' => true,
            'data' => [
                'sellings' => array_map(function($selling) use ($priceComparisons) {
                    return [
                        'id' => $selling->getId(),
                        'invoiceNumber' => $selling->getInvoiceNumber(),
                        'paymentMethod' => $selling->getPaymentMethod(),
                        'amountHT' => $selling->getAmountHT(),
                        'amountTTC' => $selling->getAmountTTC(),
                        'status' => $selling->getStatus(),
                        'items' => array_map(function($item) use ($priceComparisons) {
                            $product = $item->getProductEntity();
                            $comparison = $priceComparisons[$item->getId()] ?? [];

                            return [
                                'productName' => $product ? $product->getName() : 'Unknown Product',
                                'quantity' => $item->getQuantity(),
                                'unitPrice' => $item->getUnitPrice(),
                                'taxRate' => $item->getTaxRate(),
                                'total' => $item->getTotal(),
                                'product' => $product ? [
                                    'id' => $product->getId(),
                                    'name' => $product->getName(),
                                    'kcCode' => $product->getKcCode(),
                                    'eansCode' => $product->getEansCode(),
                                ] : null,
                                'latestInvoiceItem' => $comparison['latestInvoiceItem'] ?? null,
                                'priceDifference' => $comparison['priceDifference'] ?? null,
                                'priceDifferencePercentage' => $comparison['priceDifferencePercentage'] ?? null,
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