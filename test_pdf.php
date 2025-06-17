<?php

require_once __DIR__.'/vendor/autoload.php';

use Smalot\PdfParser\Parser;

try {
    $parser = new Parser();
    $pdf = $parser->parseFile(__DIR__ . '/FA003821.pdf');
    
    $text = $pdf->getText();
    $lines = explode("\n", $text);
    $numLines = count($lines);
    
    for ($i = 0; $i < $numLines; $i++) {
        if (strpos($lines[$i], 'Numéro de facture') !== false) {
            echo "Found 'Numéro de facture' at line $i:\n";
            for ($j = 0; $j < 6; $j++) {
                if (isset($lines[$i + $j])) {
                    echo ($i + $j) . ': ' . $lines[$i + $j] . "\n";
                }
            }
            echo "-----------------------------\n";
        }
    }
    
    // Get all text from the PDF
    $text = $pdf->getText();
    
    // Split into lines
    $lines = explode("\n", $text);
    
    // Find the table header
    $headerFound = false;
    $tableData = [];
    $headers = ['Référence', 'Produit', 'Taux de taxe', 'Prix unitaire (HT)', 'Quantité', 'Total(HT)'];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines
        if (empty($line)) {
            continue;
        }
        
        // Look for the header line
        if (strpos($line, 'Référence') !== false && strpos($line, 'Produit') !== false) {
            $headerFound = true;
            continue;
        }
        
        // If we found the header, start collecting data
        if ($headerFound) {
            // Skip lines that don't look like table data
            if (strpos($line, 'Total') !== false && strpos($line, 'TVA') !== false) {
                break;
            }
            
            // Try to split the line into columns
            // First, try to split by multiple spaces
            $columns = preg_split('/\s{2,}/', $line);
            
            // If we got the expected number of columns, add to table data
            if (count($columns) >= 6) {
                $row = [
                    'Référence' => trim($columns[0]),
                    'Produit' => trim($columns[1]),
                    'Taux de taxe' => trim($columns[2]),
                    'Prix unitaire (HT)' => trim($columns[3]),
                    'Quantité' => trim($columns[4]),
                    'Total(HT)' => trim($columns[5])
                ];
                $tableData[] = $row;
            }
        }
    }
    
    // Output the table data
    echo "Extracted Table Data:\n";
    echo "===================\n\n";
    
    // Print headers
    echo str_pad('Référence', 15) . ' | ';
    echo str_pad('Produit', 40) . ' | ';
    echo str_pad('Taux de taxe', 15) . ' | ';
    echo str_pad('Prix unitaire (HT)', 20) . ' | ';
    echo str_pad('Quantité', 10) . ' | ';
    echo str_pad('Total(HT)', 15) . "\n";
    echo str_repeat('-', 120) . "\n";
    
    // Print rows
    foreach ($tableData as $row) {
        echo str_pad($row['Référence'], 15) . ' | ';
        echo str_pad(substr($row['Produit'], 0, 38), 40) . ' | ';
        echo str_pad($row['Taux de taxe'], 15) . ' | ';
        echo str_pad($row['Prix unitaire (HT)'], 20) . ' | ';
        echo str_pad($row['Quantité'], 10) . ' | ';
        echo str_pad($row['Total(HT)'], 15) . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error processing PDF:\n";
    echo $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
} 