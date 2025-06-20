<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Document;

class PdfParserService
{
    private Parser $parser;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->parser = new Parser();
        $this->entityManager = $entityManager;
    }

    /**
     * Parse a PDF file and return its text content
     *
     * @param string $filePath Path to the PDF file
     * @return string|null The extracted text content or null if an error occurs
     */
    public function parsePdf(string $filePath): ?string
    {
        try {
            // Parse the PDF file
            $pdf = $this->parser->parseFile($filePath);
            
            // Extract text from all pages
            $text = $pdf->getText();
            
            return $text;
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            return null;
        }
    }

    /**
     * Get detailed information about the PDF
     *
     * @param string $filePath Path to the PDF file
     * @return array|null Array containing PDF details or null if an error occurs
     */
    public function getPdfDetails(string $filePath): ?array
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
            $metadata = $this->extractMetadata($text);

            if ($metadata === null) {
                return null;
            }

            return [
                'invoiceNumber' => $metadata['Numéro de facture'],
                'invoiceDate' => $metadata['Date de facturation'],
                'orderReference' => $metadata['Réf. de commande'],
                'orderDate' => $metadata['Date de commande']
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract metadata from the PDF
     *
     * @param string $filePath Path to the PDF file
     * @return array|null Array containing metadata or null if an error occurs
     */
    private function extractMetadata(string $text): ?array
    {
        try {
            $metadata = [
                'Numéro de facture' => '',
                'Date de facturation' => '',
                'Réf. de commande' => '',
                'Date de commande' => ''
            ];

            // Split text into lines
            $lines = explode("\n", $text);
            $numLines = count($lines);
            
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (strpos($line, 'Numéro de facture') !== false && strpos($line, 'Date de facturation') !== false) {
                    // Look ahead up to 3 lines for the values
                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($lines[$index + $i])) {
                            $valueLine = trim($lines[$index + $i]);
                            if (empty($valueLine)) continue;
                            // Try to extract 4 fields separated by 2+ spaces
                            $values = preg_split('/\s{2,}/', $valueLine);
                            if (count($values) >= 4) {
                                $metadata['Numéro de facture'] = trim($values[0]);
                                $metadata['Date de facturation'] = trim($values[1]);
                                $metadata['Réf. de commande'] = trim($values[2]);
                                $metadata['Date de commande'] = trim($values[3]);
                                break 2;
                            }
                        }
                    }
                }
            }
            return $metadata;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract tables from PDF and convert to JSON
     *
     * @param string $pdfPath Path to the PDF file
     * @return array|null Array containing extracted tables or null if an error occurs
     */
    public function extractTables(string $pdfPath): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $tables = [];
            $pageNumber = 1;

            foreach ($pdf->getPages() as $page) {
                $text = $page->getText();
                $lines = explode("\n", $text);

                // Extract metadata from the first page at the 20th line
                if ($pageNumber === 1) {
                    $metadataLine = trim($lines[19]); // 20th line (index 19)
                    $metadataValues = explode("\t", $metadataLine);
                    $metadata = [
                        'Numéro de facture' => $metadataValues[0] ?? '',
                        'Date de facture' => $metadataValues[1] ?? '',
                        'Référence' => $metadataValues[2] ?? '',
                        'Date de commande' => $metadataValues[3] ?? ''
                    ];
                    $lineIndex = 27; // Start reading from the 28th line (index 27)
                } else {
                    $lineIndex = 0;
                }

                // Process the rest of the lines for table data
                $headers = ['Référence', 'Code EANS', 'Produit', 'Taux de taxe', 'Prix Unitaire (HT)', 'Quantité', 'Total (HT)'];
                $rows = [];
            

                while ($lineIndex < count($lines)) {
                    $line = trim($lines[$lineIndex]);
                    if (empty($line)) {
                        $lineIndex++;
                        continue;
                    }

                    $columns = preg_split('/\s{2,}/', $line);
                    $columns = array_map('trim', $columns);

                    if (count($columns) >= 1) {
                        // Check if the first column starts with 'PRD'
                        if (strpos($columns[0], 'PRD') === 0) {
                            // Start of a new invoice item
                            $itemLines = [];
                            $itemLines[] = $columns; // Add the current line to the item lines

                            // Read the next lines until the next 'PRD' is found
                            $nextLineIndex = $lineIndex + 1;
                            while ($nextLineIndex < count($lines)) {
                                $nextLine = trim($lines[$nextLineIndex]);
                                if (empty($nextLine)) {
                                    $nextLineIndex++;
                                    continue;
                                }
                                $nextColumns = preg_split('/\s{2,}/', $nextLine);
                                $nextColumns = array_map('trim', $nextColumns);
                                if (count($nextColumns) >= 1 && strpos($nextColumns[0], 'PRD') === 0) {
                                    break; // New item found
                                }
                                $itemLines[] = $nextColumns; // Add the line to the item lines
                                $nextLineIndex++;
                            }

                            // Process the item lines to create an invoice item
                            $itemData = [];
                            $taxeRateIndex = 0;
                            foreach ($itemLines as $itemLine) {
                                $itemData = array_merge($itemData, $itemLine);
                            }

                            // Extract Code EANS (13 digits)
                            preg_match('/\d{13}/', $itemData[1], $matches);
                            $codeEANS = $matches[0] ?? '';
                            // Extract Produit (everything between Code EANS and '20 %')
                            $produit = trim(substr($itemData[1], strlen($codeEANS)));
                            if (strpos($produit, '20 %') !== false) {
                                $produit = trim(substr($produit, 0, strpos($produit, '20 %')));
                            }
                            for ($i = 2; $i < count($itemData); $i++) {
                                if (strpos($itemData[$i], '20 %') !== false) {
                                    break;
                                }
                            
                                $produit .= ' ' . $itemData[$i] . ' ';
                            }
                        
                            $produit = trim($produit);
                            foreach ($itemData as $item) {
                                if (strpos($item, '20 %') !== false) {
                                    $taxeRateIndex = array_search($item, $itemData);
                                    break;
                                }
                            }
                            // Concatenate all the $itemData from $taxeRateIndex to the end with a space between them
                            $itemDataString = implode(' ', array_slice($itemData, $taxeRateIndex));
                            // Use regex to extract $prixUnitaire, $quantity, and $total
                            preg_match('/20 %\s*([^€]+)€\s*(\d+)\s*([^€]+)€/', $itemDataString, $matches);
                     
                            if (count($matches) >= 4) {
                                $prixUnitaire = trim($matches[1]);
                                $quantity = (int)$matches[2];
                                $total = trim($matches[3]);
                            } else {
                                $prixUnitaire = null;
                                $quantity = null;
                                $total = null;
                            }
                            // Construct the row
                            $row = [
                                'Référence' => $itemData[0],
                                'Code EANS' => $codeEANS,
                                'Produit' => $produit,
                                'Taux de taxe' => '20 %',
                                'Prix Unitaire (HT)' => $prixUnitaire,
                                'Quantité' => $quantity,
                                'Total (HT)' => $total
                            ];
                            $rows[] = $row;
                            $lineIndex = $nextLineIndex; // Update the line index to the next item
                        } else {
                            $lineIndex++;
                        }
                    } else {
                        $lineIndex++;
                    }
                }

                if (!empty($rows)) {
                    $tables["page_{$pageNumber}"] = [
                        'headers' => $headers,
                        'rows' => $rows,
                        'metadata' => $metadata ?? []
                    ];
                }

                $pageNumber++;
            }
            
            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function saveTablesToDatabase(array $tables): void
    {
        // Create a single invoice for all pages
        $invoice = new Invoice();
        $invoice->setInvoiceNumber($tables['page_1']['metadata']['Numéro de facture']);
        // Format the date string to ensure it is in a valid format
        $invoiceDate = \DateTime::createFromFormat('d/m/Y', $tables['page_1']['metadata']['Date de facture']);
        if ($invoiceDate) {
            $invoice->setInvoiceDate($invoiceDate);
        } else {
            // Handle invalid date format
            throw new \Exception("Invalid invoice date format: " . $tables['page_1']['metadata']['Date de facture']);
        }
        $invoice->setOrderReference($tables['page_1']['metadata']['Référence']);
        $invoice->setTotalAmount(0);
        // Format the order date string to ensure it is in a valid format
        $orderDate = \DateTime::createFromFormat('d/m/Y', $tables['page_1']['metadata']['Date de commande']);
        if ($orderDate) {
            $invoice->setOrderDate($orderDate);
        } else {
            // Handle invalid date format
            throw new \Exception("Invalid order date format: " . $tables['page_1']['metadata']['Date de commande']);
        }

        // Persist the invoice
        $this->entityManager->persist($invoice);

        // Create invoice items for all pages
        foreach ($tables as $pageKey => $table) {
            $rows = $table['rows'];
            foreach ($rows as $row) {
                // Find or create product
                $product = $this->findOrCreateProduct($row['Référence'], $row['Code EANS'], $row['Produit']);
                
                $invoiceItem = new InvoiceItem();
                $invoiceItem->setTaxRate($row['Taux de taxe']);
                // Convert price strings to numbers (remove € and spaces)
                $unitPrice = str_replace(['€', ' '], '', $row['Prix Unitaire (HT)']);
                $unitPrice = str_replace(',', '.', $unitPrice);
                $totalPrice = str_replace(['€', ' '], '', $row['Total (HT)']);
                $totalPrice = str_replace(',', '.', $totalPrice);
                $invoiceItem->setUnitPrice((float) $unitPrice);
                $invoiceItem->setQuantity($row['Quantité']);
                $invoiceItem->setTotal((float) $totalPrice);
                $invoiceItem->setInvoice($invoice);
                $invoiceItem->setProductEntity($product);

                // Persist the invoice item
                $this->entityManager->persist($invoiceItem);
            }
        }

        // Flush changes to the database
        $this->entityManager->flush();
    }

    private function findOrCreateProduct(string $reference, string $eansCode, string $productName): Product
    {
        // Try to find existing product by kc_code
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['kcCode' => $reference]);
        
        if (!$product) {
            // Create new product
            $product = new Product();
            $product->setKcCode($reference);
            $product->setEansCode($eansCode);
            $product->setName($productName);
            $this->entityManager->persist($product);
        }
        
        return $product;
    }

    private function saveToDatabase(array $metadata, array $rows): void
    {
        // Create and save Invoice
        $invoice = new Invoice();
        $invoice->setInvoiceNumber($metadata['Numéro de facture']);
        $invoice->setInvoiceDate(\DateTime::createFromFormat('d/m/Y', $metadata['Date de facturation']));
        $invoice->setOrderReference($metadata['Réf. de commande']);
        $invoice->setOrderDate(\DateTime::createFromFormat('d/m/Y', $metadata['Date de commande']));

        $totalAmount = 0;

        // Create and save InvoiceItems
        foreach ($rows as $row) {
            // Find or create product
            $product = $this->findOrCreateProduct($row['Référence'], $row['Code EANS'], $row['Produit']);
            
            $item = new InvoiceItem();
            $item->setTaxRate($row['Taux de taxe']);
            
            // Convert price strings to numbers (remove € and spaces)
            $unitPrice = str_replace(['€', ' '], '', $row['Prix unitaire (HT)']);
            $unitPrice = str_replace(',', '.', $unitPrice);

            $totalPrice = str_replace(['€', ' '], '', $row['Total(HT)']);
            $totalPrice = str_replace(',', '.', $totalPrice);
            
            $item->setUnitPrice($unitPrice);
            $item->setQuantity((int)$row['Quantité']);
            $item->setTotal($totalPrice);
            $item->setProductEntity($product);
            
            $invoice->addItem($item);
            $totalAmount += (float)$totalPrice;
        }

        // Set the total amount for the invoice
        $invoice->setTotalAmount($totalAmount);

        // Save to database
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();
    }

    /**
     * Process a single page to extract tables
     *
     * @param \Smalot\PdfParser\Page $page
     * @return array
     */
    private function processPageForTables($page): array
    {
        $tables = [];
        $text = $page->getText();
        
        // Split text into lines
        $lines = explode("\n", $text);
        
        // Find potential table rows
        $currentTable = [];
        $inTable = false;
        
        foreach ($lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }
            
            // Check if line contains multiple columns (separated by multiple spaces or tabs)
            if (preg_match('/\S+\s{2,}\S+/', $line)) {
                if (!$inTable) {
                    $inTable = true;
                }
                
                // Split the line into columns
                $columns = preg_split('/\s{2,}/', trim($line));
                $currentTable[] = $columns;
            } else {
                // If we were in a table and now we're not, save the table
                if ($inTable && !empty($currentTable)) {
                    $tables[] = $this->formatTable($currentTable);
                    $currentTable = [];
                }
                $inTable = false;
            }
        }
        
        // Save the last table if exists
        if ($inTable && !empty($currentTable)) {
            $tables[] = $this->formatTable($currentTable);
        }
        
        return $tables;
    }

    /**
     * Format a table array into a structured format
     *
     * @param array $table
     * @return array
     */
    private function formatTable(array $table): array
    {
        if (empty($table)) {
            return [];
        }

        // Use first row as headers
        $headers = array_map('trim', $table[0]);
        
        // Process remaining rows
        $rows = [];
        for ($i = 1; $i < count($table); $i++) {
            $row = array_map('trim', $table[$i]);
            // Ensure row has same number of columns as headers
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $rows[] = array_combine($headers, $row);
        }
        
        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }
} 