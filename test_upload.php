<?php

try {
    $pdfPath = __DIR__ . '/FA003821.pdf';
    if (!file_exists($pdfPath)) {
        throw new Exception("PDF file not found at: $pdfPath");
    }
    
    echo "Sending request to upload PDF...\n";
    
    $ch = curl_init('http://localhost:8000/api/pdf/upload');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'pdf' => new CURLFile($pdfPath, 'application/pdf', 'FA003821.pdf')
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    echo "\nHTTP Status Code: $httpCode\n";
    echo "Response:\n";
    $json = json_decode($response, true);
    if ($json === null) {
        echo $response;
    } else {
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 