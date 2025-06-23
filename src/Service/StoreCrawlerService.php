<?php

namespace App\Service;

use App\Entity\Store;
use App\Entity\Selling;
use App\Entity\SellingItem;
use App\Repository\SellingRepository;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class StoreCrawlerService
{
    private $logger;
    private $browser;
    private $logFile;
    private $entityManager;
    private $sellingRepository;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        SellingRepository $sellingRepository
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->sellingRepository = $sellingRepository;
        $this->browser = new HttpBrowser(HttpClient::create([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]));
        $this->logFile = dirname(__DIR__, 2) . '/var/log/store_crawler.log';
    }

    private function logToFile(string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        if (!empty($context)) {
            $logMessage .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        $logMessage .= "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    private function getLoginUrl(string $baseUrl): string
    {
        // Remove trailing slash from base URL if present
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/takepos/index.php?idmenu=1&mainmenu=takepos&leftmenu=';
    }

    private function getInvoiceListUrl(string $baseUrl, int $limit = 10): string
    {
        // Remove trailing slash from base URL if present
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/compta/facture/list.php?contextpage=poslist&limit=' . $limit;
    }

    private function extractInvoiceDetails(Crawler $crawler): array
    {
        $invoices = [];
        try {
            // Find all rows with class oddeven
            $rows = $crawler->filter('tr.oddeven');
            
            foreach ($rows as $row) {
                $rowCrawler = new Crawler($row);
                $cells = $rowCrawler->filter('td');
                
                if ($cells->count() >= 11) {
                    // Extract invoice number from the first cell, removing any additional text
                    $invoiceNumberCell = $cells->eq(0);
                    $fullText = trim($invoiceNumberCell->filter('table tr td:first-child')->text());
                    
                    // Extract only the TC1 number using regex
                    if (preg_match('/TC1-\d+-\d+/', $fullText, $matches)) {
                        $invoiceNumber = $matches[0];
                        
                        // Only process invoices starting with TC1
                        if (strpos($invoiceNumber, 'TC1') === 0) {
                            // Get all cell contents for debugging
                            $this->logToFile('Cell contents:');
                            for ($i = 0; $i < $cells->count(); $i++) {
                                $this->logToFile("Cell $i: " . trim($cells->eq($i)->text()));
                            }

                            // Parse date from DD/MM/YYYY format
                            $dateStr = trim($cells->eq(2)->text());
                            $this->logToFile('Attempting to parse date: ' . $dateStr);
                            
                            $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
                            if (!$date) {
                                $this->logToFile('Error parsing date: ' . $dateStr);
                                $this->logToFile('Date cell HTML: ' . $cells->eq(2)->html());
                                continue;
                            }

                            $invoice = [
                                'invoiceNumber' => $invoiceNumber,
                                'date' => $date,
                                'paymentMethod' => trim($cells->eq(4)->text()),
                                'amountHT' => $this->parseAmount(trim($cells->eq(5)->text())),
                                'amountTTC' => $this->parseAmount(trim($cells->eq(6)->text())),
                                'status' => trim($cells->eq(10)->filter('.badge')->text()),
                                'detailsUrl' => $this->extractDetailsUrl($rowCrawler)
                            ];
                            
                            $invoices[] = $invoice;
                            $this->logToFile('Found invoice: ' . json_encode($invoice));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logToFile('Error extracting invoice details: ' . $e->getMessage());
            $this->logToFile('HTML content: ' . $crawler->html());
        }
        
        return $invoices;
    }

    private function parseAmount(string $amount): float
    {
        // Remove any non-numeric characters except decimal point
        $amount = str_replace(['€', ' ', ','], ['', '', '.'], $amount);
        return (float) $amount;
    }

    private function extractDetailsUrl(Crawler $crawler): ?string
    {
        try {
            $row = $crawler->closest('tr');
            $onclick = $row->attr('onclick');
            
            if (!$onclick) {
                $this->logToFile("No onclick attribute found in row");
                return null;
            }

            // Extract URL from load('url') format
            if (preg_match("/load\('([^']+)'/", $onclick, $matches)) {
                $relativeUrl = $matches[1];
                $this->logToFile("Extracted relative URL: " . $relativeUrl);
                return $relativeUrl;
            }

            $this->logToFile("Could not extract URL from onclick: " . $onclick);
            return null;
        } catch (\Exception $e) {
            $this->logToFile("Error extracting details URL: " . $e->getMessage());
            return null;
        }
    }

    private function createSelling(Store $store, array $invoice): ?Selling
    {
        try {
            // Check if invoice number already exists
            if ($this->sellingRepository->invoiceNumberExists($invoice['invoiceNumber'])) {
                $this->logToFile("Invoice {$invoice['invoiceNumber']} already exists, skipping...");
                return null;
            }

            $selling = new Selling();
            $selling->setStore($store);
            $selling->setInvoiceNumber($invoice['invoiceNumber']);
            $selling->setDate($invoice['date']);
            $selling->setPaymentMethod($invoice['paymentMethod']);
            $selling->setAmountHT($invoice['amountHT']);
            $selling->setAmountTTC($invoice['amountTTC']);
            $selling->setStatus($invoice['status']);
            $selling->setDetailsUrl($invoice['detailsUrl']);

            // Crawl invoice details
            $details = $this->crawlInvoiceDetails($store, $invoice['detailsUrl']);
            $hasWarning = false;
            if ($details && isset($details['lines'])) {
                foreach ($details['lines'] as $line) {
                    $item = new SellingItem();
                    $item->setQuantity($line['quantity']);
                    $item->setTaxRate($line['tax_rate']);
                    $item->setTotal($line['total_ttc']);

                    // Only calculate unit price if quantity is not null/0
                    if (!empty($line['quantity'])) {
                        if (isset($line['total_ht'])) {
                            $item->setUnitPrice($line['total_ht'] / $line['quantity']);
                        } else {
                            $item->setUnitPrice($line['total_ttc'] / $line['quantity']);
                        }
                    } else {
                        $hasWarning = true;
                    }

                    // Link to Product based on description
                    if (isset($line['description']) && !empty($line['description'])) {
                        $product = $this->findOrCreateProductByName($line['description']);
                        $item->setProductEntity($product);
                    }

                    $selling->addItem($item);
                }
                $this->logToFile("Added " . count($details['lines']) . " items to invoice {$invoice['invoiceNumber']}");
            }

            // If any line had null/0 quantity, set status to 'warning'
            if ($hasWarning) {
                $selling->setStatus('warning');
            }

            $this->entityManager->persist($selling);
            $this->entityManager->flush();

            $this->logToFile("Created selling record for invoice {$invoice['invoiceNumber']}");
            return $selling;
        } catch (\Exception $e) {
            $this->logToFile("Error creating selling record: " . $e->getMessage());
            return null;
        }
    }

    private function createClient()
    {
        return HttpClient::create([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    private function extractInvoiceLineDetails(string $html): array
    {
        $crawler = new Crawler($html);
        $lines = [];
        
        try {
            // Log the number of matching rows found
            $rowCount = $crawler->filter('tr.posinvoiceline')->count();
            $this->logToFile("Found {$rowCount} invoice line rows");

            $crawler->filter('tr.posinvoiceline')->each(function (Crawler $row) use (&$lines) {
                try {
                    $line = [
                        'description' => trim($row->filter('td.left')->text()),
                        'tax_rate' => trim($row->filter('td.right')->eq(0)->text()),
                        'quantity' => (int)trim($row->filter('td.right')->eq(1)->text()),
                        'total_ttc' => $this->parseAmount(trim($row->filter('td.right')->eq(2)->text())),
                    ];

                    // Extract tooltip details if available
                    $tooltip = $row->filter('td.right.classfortooltip')->attr('title');
                    if ($tooltip) {
                        if (preg_match('/Total HT: ([0-9,]+)/', $tooltip, $matches)) {
                            $line['total_ht'] = $this->parseAmount($matches[1]);
                        }
                        if (preg_match('/Total TVA: ([0-9,]+)/', $tooltip, $matches)) {
                            $line['total_tva'] = $this->parseAmount($matches[1]);
                        }
                        if (preg_match('/Total Taxe 2: ([0-9,]+)/', $tooltip, $matches)) {
                            $line['total_taxe2'] = $this->parseAmount($matches[1]);
                        }
                        if (preg_match('/Total Taxe 3: ([0-9,]+)/', $tooltip, $matches)) {
                            $line['total_taxe3'] = $this->parseAmount($matches[1]);
                        }
                    }

                    $lines[] = $line;
                    $this->logToFile("Successfully extracted line: " . json_encode($line));
                } catch (\Exception $e) {
                    $this->logToFile("Error extracting line: " . $e->getMessage());
                }
            });

            $this->logToFile("Extracted " . count($lines) . " invoice lines");
            return $lines;
        } catch (\Exception $e) {
            $this->logToFile("Error extracting invoice lines: " . $e->getMessage());
            return [];
        }
    }

    private function crawlInvoiceDetails(Store $store, string $detailsUrl): ?array
    {
        try {
            $this->logToFile("Crawling invoice details from URL: " . $detailsUrl);

            // Get the base URL from the store's URL
            $baseUrl = $this->getBaseUrl($store->getUrl());
            
            // Construct the full URL with 'takepos' in the path
            $fullUrl = $baseUrl . '/takepos/' . ltrim($detailsUrl, '/');
            $this->logToFile("Full URL for invoice details: " . $fullUrl);

            // Use the existing browser session to fetch invoice details
            $crawler = $this->browser->request('GET', $fullUrl);
            $content = $crawler->html();
            
            // Log the raw HTML for debugging
            $this->logToFile("Raw HTML response: " . $content);
            
            // Extract invoice line details
            $invoiceLines = $this->extractInvoiceLineDetails($content);
            
            // Log the extracted details
            $this->logToFile("Invoice details: " . json_encode($invoiceLines, JSON_PRETTY_PRINT));

            return [
                'url' => $fullUrl,
                'lines' => $invoiceLines
            ];

        } catch (\Exception $e) {
            $this->logToFile("Error crawling invoice details: " . $e->getMessage());
            return null;
        }
    }

    private function getBaseUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    }

    /**
     * Find or create a Product by name
     */
    private function findOrCreateProductByName(string $productName): ?\App\Entity\Product
    {
        try {
            // Try to find existing product by name (exact match)
            $product = $this->entityManager->getRepository(\App\Entity\Product::class)
                ->findOneBy(['name' => $productName]);
            
            if ($product) {
                return $product;
            }
            
            // Try to find by name using LIKE (partial match)
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('p')
               ->from(\App\Entity\Product::class, 'p')
               ->where('p.name LIKE :name')
               ->setParameter('name', '%' . $productName . '%')
               ->setMaxResults(1);
            
            $product = $qb->getQuery()->getOneOrNullResult();
            
            if ($product) {
                return $product;
            }
            
            // Create new product if not found
            $product = new \App\Entity\Product();
            $product->setName($productName);
            $product->setKcCode('AUTO-' . substr(md5($productName), 0, 8)); // Generate a unique KC code
            $product->setEansCode(''); // Empty EAN code for auto-created products
            
            $this->entityManager->persist($product);
            $this->logToFile("Created new product: {$productName}");
            
            return $product;
        } catch (\Exception $e) {
            $this->logToFile("Error finding/creating product for name '{$productName}': " . $e->getMessage());
            return null;
        }
    }

    public function crawlStore(Store $store, int $limit = 10): array
    {
        try {
            $baseUrl = $store->getUrl();
            $loginUrl = $this->getLoginUrl($baseUrl);
            $invoiceListUrl = $this->getInvoiceListUrl($baseUrl, $limit);

            // Step 1: Visit the login URL
            $this->logger->info('Visiting login URL: ' . $loginUrl);
            $this->logToFile('Visiting login URL', ['url' => $loginUrl]);
            $crawler = $this->browser->request('GET', $loginUrl);

            // Log the initial page content
            $this->logToFile('Initial page content', [
                'html' => $crawler->html(),
                'status' => $this->browser->getResponse()->getStatusCode()
            ]);

            // Try to find the login form by its ID and action
            $form = null;
            try {
                $form = $crawler->filter('form#login[action*="/takepos/index.php?mainmenu=home"]')->form();
                $this->logToFile('Found login form by ID and action');
            } catch (\Exception $e) {
                $this->logToFile('Failed to find form by ID and action', ['error' => $e->getMessage()]);
                
                // Try finding by ID only
                try {
                    $form = $crawler->filter('form#login')->form();
                    $this->logToFile('Found login form by ID only');
                } catch (\Exception $e) {
                    $this->logToFile('Failed to find form by ID', ['error' => $e->getMessage()]);
                    
                    // Try finding by action only
                    try {
                        $form = $crawler->filter('form[action*="/takepos/index.php?mainmenu=home"]')->form();
                        $this->logToFile('Found login form by action only');
                    } catch (\Exception $e) {
                        $this->logToFile('Failed to find form by action', ['error' => $e->getMessage()]);
                    }
                }
            }

            if (!$form) {
                throw new \Exception('Could not find login form');
            }
            
            // Set all required form fields
            $form->setValues([
                'token' => '',
                'actionlogin' => 'login',
                'loginfunction' => 'loginfunction',
                'tz' => '1',
                'tz_string' => 'Europe/Paris',
                'dst_observed' => '1',
                'dst_first' => '2025-03-30T01:59:00Z',
                'dst_second' => '2025-10-26T02:59:00Z',
                'screenwidth' => '1512',
                'screenheight' => '751',
                'dol_hide_topmenu' => '',
                'dol_hide_leftmenu' => '',
                'dol_optimize_smallscreen' => '',
                'dol_no_mouse_hover' => '',
                'dol_use_jmobile' => '',
                'username' => $store->getUsername(),
                'password' => $store->getPassword(),
                'totp2fa' => ''
            ]);

            // Step 3: Submit the login form
            $this->logger->info('Submitting login form');
            $this->logToFile('Submitting login form', [
                'username' => $store->getUsername(),
                'url' => $loginUrl
            ]);
            $crawler = $this->browser->submit($form);

            // Log the response after login
            $this->logToFile('Login response', [
                'status' => $this->browser->getResponse()->getStatusCode(),
                'content' => $crawler->html()
            ]);

            // Step 4: Make GET request to invoice list endpoint
            $this->logger->info('Requesting invoice list');
            $this->logToFile('Requesting invoice list', ['url' => $invoiceListUrl]);
            
            $crawler = $this->browser->request('GET', $invoiceListUrl);

            // Log the response
            $this->logToFile('Invoice list response', [
                'status' => $this->browser->getResponse()->getStatusCode(),
                'content' => $crawler->html()
            ]);

            // Extract invoice details and create selling records
            $invoices = $this->extractInvoiceDetails($crawler);
            $createdSellings = [];
            
            foreach ($invoices as $invoice) {
                $selling = $this->createSelling($store, $invoice);
                if ($selling) {
                    $createdSellings[] = $selling;
                }
            }
            
            return [
                'success' => true,
                'created_sellings' => $createdSellings
            ];

        } catch (TransportException $e) {
            $this->logger->error('Network error: ' . $e->getMessage());
            $this->logToFile('Network error', [
                'error' => $e->getMessage(),
                'store' => $store->getName(),
                'url' => $store->getUrl()
            ]);
            return [
                'success' => false,
                'error' => 'Network error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error crawling store: ' . $e->getMessage());
            $this->logToFile('Error crawling store', [
                'error' => $e->getMessage(),
                'store' => $store->getName(),
                'url' => $store->getUrl()
            ]);
            return [
                'success' => false,
                'error' => 'Error crawling store: ' . $e->getMessage()
            ];
        }
    }
} 