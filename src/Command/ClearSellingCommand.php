<?php

namespace App\Command;

use App\Repository\SellingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-selling',
    description: 'Clear all selling records from the database',
)]
class ClearSellingCommand extends Command
{
    private $entityManager;
    private $sellingRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SellingRepository $sellingRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->sellingRepository = $sellingRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->warning('This will delete ALL selling records from the database.');
        if (!$io->confirm('Are you sure you want to continue?', false)) {
            $io->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        try {
            $count = $this->sellingRepository->count([]);
            $this->sellingRepository->createQueryBuilder('s')
                ->delete()
                ->getQuery()
                ->execute();

            $io->success(sprintf('Successfully deleted %d selling records.', $count));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while deleting selling records: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 