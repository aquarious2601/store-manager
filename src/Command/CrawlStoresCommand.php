<?php

namespace App\Command;

use App\Entity\Store;
use App\Service\StoreCrawlerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:crawl-stores',
    description: 'Crawl all stores',
)]
class CrawlStoresCommand extends Command
{
    private $entityManager;
    private $storeCrawlerService;

    public function __construct(
        EntityManagerInterface $entityManager,
        StoreCrawlerService $storeCrawlerService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->storeCrawlerService = $storeCrawlerService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting store crawler');
        $io->section('======================');

        $stores = $this->entityManager->getRepository(Store::class)->findAll();

        foreach ($stores as $store) {
            $io->section('Crawling store: ' . $store->getName());
            $io->section('----------------------------------');

            try {
                $result = $this->storeCrawlerService->crawlStore($store);
                $io->success('Successfully crawled store: ' . $store->getName());
            } catch (\Exception $e) {
                $io->error('Error crawling store ' . $store->getName() . ': ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
} 