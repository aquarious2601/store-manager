<?php

namespace App\Controller;

use App\Service\PdfParserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class PdfController extends AbstractController
{
    public function __construct(
        private readonly PdfParserService $pdfParserService,
        private readonly EntityManagerInterface $entityManager
    ) {}

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

            $filePath = $file->getPathname();
            
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

            return $this->json([
                'success' => true,
                'message' => 'PDF uploaded and processed successfully',
                'filename' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            error_log("Error in uploadPdf: " . $e->getMessage());
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/pdf/upload-multiple', name: 'app_pdf_upload_multiple', methods: ['POST'])]
    public function uploadMultiplePdfs(Request $request): JsonResponse
    {
        try {
            $files = $request->files->get('pdfs');
            
            if (!$files || empty($files)) {
                return $this->json(['error' => 'No files uploaded'], 400);
            }

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            // Handle both single file and multiple files
            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (!$file || $file->getClientMimeType() !== 'application/pdf') {
                    $errorCount++;
                    $results[] = [
                        'filename' => $file ? $file->getClientOriginalName() : 'Unknown',
                        'success' => false,
                        'error' => 'Invalid file type or no file'
                    ];
                    continue;
                }

                $filePath = $file->getPathname();
                
                if (!file_exists($filePath) || !is_readable($filePath)) {
                    $errorCount++;
                    $results[] = [
                        'filename' => $file->getClientOriginalName(),
                        'success' => false,
                        'error' => 'File is not accessible'
                    ];
                    continue;
                }

                try {
                    $tables = $this->pdfParserService->extractTables($filePath);

                    if (isset($tables['error'])) {
                        $errorCount++;
                        $results[] = [
                            'filename' => $file->getClientOriginalName(),
                            'success' => false,
                            'error' => $tables['error']
                        ];
                        continue;
                    }

                    if ($tables === null || empty($tables)) {
                        $errorCount++;
                        $results[] = [
                            'filename' => $file->getClientOriginalName(),
                            'success' => false,
                            'error' => 'Failed to extract tables from PDF'
                        ];
                        continue;
                    }

                    $this->pdfParserService->saveTablesToDatabase($tables);
                    
                    $successCount++;
                    $results[] = [
                        'filename' => $file->getClientOriginalName(),
                        'success' => true,
                        'message' => 'Processed successfully'
                    ];
                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'filename' => $file->getClientOriginalName(),
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->json([
                'success' => true,
                'summary' => [
                    'total' => count($files),
                    'successful' => $successCount,
                    'failed' => $errorCount
                ],
                'results' => $results
            ]);
        } catch (\Exception $e) {
            error_log("Error in uploadMultiplePdfs: " . $e->getMessage());
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/pdf/upload-folder', name: 'app_pdf_upload_folder', methods: ['POST'])]
    public function uploadFolder(Request $request): JsonResponse
    {
        try {
            $folderPath = $request->request->get('folder_path');
            
            if (!$folderPath || !is_dir($folderPath)) {
                return $this->json(['error' => 'Invalid folder path'], 400);
            }

            $pdfFiles = $this->findPdfFilesInFolder($folderPath);
            
            if (empty($pdfFiles)) {
                return $this->json(['error' => 'No PDF files found in the specified folder'], 400);
            }

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($pdfFiles as $filePath) {
                $filename = basename($filePath);
                
                try {
                    $tables = $this->pdfParserService->extractTables($filePath);

                    if (isset($tables['error'])) {
                        $errorCount++;
                        $results[] = [
                            'filename' => $filename,
                            'success' => false,
                            'error' => $tables['error']
                        ];
                        continue;
                    }

                    if ($tables === null || empty($tables)) {
                        $errorCount++;
                        $results[] = [
                            'filename' => $filename,
                            'success' => false,
                            'error' => 'Failed to extract tables from PDF'
                        ];
                        continue;
                    }

                    $this->pdfParserService->saveTablesToDatabase($tables);
                    
                    $successCount++;
                    $results[] = [
                        'filename' => $filename,
                        'success' => true,
                        'message' => 'Processed successfully'
                    ];
                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'filename' => $filename,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->json([
                'success' => true,
                'summary' => [
                    'total' => count($pdfFiles),
                    'successful' => $successCount,
                    'failed' => $errorCount
                ],
                'results' => $results
            ]);
        } catch (\Exception $e) {
            error_log("Error in uploadFolder: " . $e->getMessage());
            return $this->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    private function findPdfFilesInFolder(string $folderPath): array
    {
        $pdfFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $pdfFiles[] = $file->getPathname();
            }
        }

        return $pdfFiles;
    }
}
