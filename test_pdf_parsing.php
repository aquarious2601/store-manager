<?php

require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

$parser = new Parser();
$filePath = 'FA003821.pdf';

try {
    $pdf = $parser->parseFile($filePath);
    $text = $pdf->getText();
    echo "Extracted PDF text:\n" . $text;
} catch (\Exception $e) {
    echo "Error parsing PDF: " . $e->getMessage();
} 