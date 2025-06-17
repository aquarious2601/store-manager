<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Request\Body\MultipartStream;
use Symfony\Component\HttpClient\Request\Body\DataPart;

try {
    $client = HttpClient::create();
    
    $pdfPart = DataPart::fromPath(__DIR__ . '/FA003821.pdf', 'FA003821.pdf', 'application/pdf');
    $body = [
        'pdf' => $pdfPart,
    ];
    $boundary = '----WebKitFormBoundary'.md5(uniqid());
    $stream = new MultipartStream($body, $boundary);

    $response = $client->request('POST', 'http://localhost:8000/api/pdf/tables', [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        ],
        'body' => $stream,
    ]);

    $content = $response->getContent(false);
    $json = json_decode($content, true);
    if ($json === null) {
        echo $content;
    } else {
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 