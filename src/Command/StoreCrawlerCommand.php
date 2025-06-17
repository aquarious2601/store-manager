<?php

namespace App\Command;

use App\Entity\Store;
use App\Service\StoreCrawlerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:store:crawl',
    description: 'Crawl store data',
)]
class StoreCrawlerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StoreCrawlerService $storeCrawlerService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store ID to crawl')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of invoices to crawl', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $storeId = $input->getOption('store');
        $limit = (int) $input->getOption('limit');

        if ($storeId) {
            $store = $this->entityManager->getRepository(Store::class)->find($storeId);
            if (!$store) {
                $io->error('Store not found');
                return Command::FAILURE;
            }

            $io->info(sprintf('Crawling store: %s (ID: %d)', $store->getName(), $store->getId()));
            $result = $this->storeCrawlerService->crawlStore($store, $limit);

            if ($result['success']) {
                $io->success(sprintf('Successfully crawled %d invoices for store %s', count($result['created_sellings']), $store->getName()));
            } else {
                $io->error('Error crawling store: ' . $result['error']);
                return Command::FAILURE;
            }
        } else {
            $stores = $this->entityManager->getRepository(Store::class)->findAll();
            if (empty($stores)) {
                $io->error('No stores found');
                return Command::FAILURE;
            }

            foreach ($stores as $store) {
                $io->info(sprintf('Crawling store: %s (ID: %d)', $store->getName(), $store->getId()));
                $result = $this->storeCrawlerService->crawlStore($store, $limit);

                if ($result['success']) {
                    $io->success(sprintf('Successfully crawled %d invoices for store %s', count($result['created_sellings']), $store->getName()));
                } else {
                    $io->error('Error crawling store: ' . $result['error']);
                    return Command::FAILURE;
                }
            }
        }

        return Command::SUCCESS;
    }
} 