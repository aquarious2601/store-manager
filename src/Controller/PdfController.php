<?php

namespace App\Controller;

use App\Service\PdfParserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\InvoiceItem;

class PdfController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        private readonly PdfParserService $pdfParserService,
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/pdf/parse', name: 'app_pdf_parse', methods: ['POST'])]
    public function parsePdf(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('pdf');
            
            if (!$file) {
                return $this->json(['error' => 'No PDF file uploaded'], 400);
            }

            if ($file->getClientMimeType() !== 'application/pdf') {
                return $this->json(['error' => 'File must be a PDF'], 400);
            }

            $filePath = $file->getPathname();
            $text = $this->pdfParserService->parsePdf($filePath);

            if ($text === null) {
                return $this->json(['error' => 'Failed to parse PDF'], 500);
            }

            return $this->json([
                'success' => true,
                'text' => $text
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/pdf/details', name: 'app_pdf_details', methods: ['POST'])]
    public function getPdfDetails(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('pdf');
            
            if (!$file) {
                return $this->json(['error' => 'No PDF file uploaded'], 400);
            }

            if ($file->getClientMimeType() !== 'application/pdf') {
                return $this->json(['error' => 'File must be a PDF'], 400);
            }

            $filePath = $file->getPathname();
            $details = $this->pdfParserService->getPdfDetails($filePath);

            if ($details === null) {
                return $this->json(['error' => 'Failed to get PDF details'], 500);
            }

            return $this->json([
                'success' => true,
                'details' => $details
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/invoices/upload', name: 'app_invoices_upload', methods: ['POST'])]
    public function uploadInvoice(Request $request): JsonResponse
    {
        $file = $request->files->get('pdf');
        if (!$file) {
            return new JsonResponse(['error' => 'No PDF file uploaded'], Response::HTTP_BAD_REQUEST);
        }
        $result = $this->pdfParserService->extractTables($file);
        return new JsonResponse(['message' => 'Invoice uploaded successfully']);
    }

    #[Route('/api/pdf/upload', name: 'app_pdf_upload', methods: ['POST'])]
    public function uploadPdf(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('pdf');
            
            if (!$file) {
                return $this->json(['error' => 'No PDF file uploaded'], 400);
            }

            if ($file->getClientMimeType() !== 'application/pdf') {
                return $this->json(['error' => 'File must be a PDF'], 400);
            }

            // Get the file path and extract tables
            $filePath = $file->getPathname();
            
            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return $this->json(['error' => 'PDF file is not accessible'], 500);
            }

            $tables = $this->pdfParserService->extractTables($filePath);

            if (isset($tables['error'])) {
                return $this->json(['error' => $tables['error']], 500);
            }

            if ($tables === null || empty($tables)) {
                return $this->json(['error' => 'Failed to extract tables from PDF'], 500);
            }

            $this->pdfParserService->saveTablesToDatabase($tables);

            // TEMP: Return the tables array for development/testing
            return $this->json([
                'success' => true,
                'tables' => $tables
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("Error in uploadPdf: " . $e->getMessage());
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
} 