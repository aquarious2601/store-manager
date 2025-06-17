<?php

namespace App\Command;

use App\Entity\SellingItem;
use App\Service\SellingItemLinkerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:link-selling-items',
    description: 'Link selling items to invoice items based on description',
)]
class LinkSellingItemsCommand extends Command
{
    private $entityManager;
    private $linkerService;

    public function __construct(EntityManagerInterface $entityManager, SellingItemLinkerService $linkerService)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->linkerService = $linkerService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Linking Selling Items to Invoice Items');

        $batchSize = 100;
        $offset = 0;
        $totalItems = 0;

        // Count total SellingItems
        $totalItems = $this->entityManager->getRepository(SellingItem::class)
            ->createQueryBuilder('si')
            ->select('COUNT(si.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $io->writeln(sprintf('Found %d SellingItems to process', $totalItems));

        while (true) {
            $sellingItems = $this->entityManager->getRepository(SellingItem::class)
                ->createQueryBuilder('si')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if (count($sellingItems) === 0) {
                break;
            }

            $result = $this->linkerService->linkSellingItems($sellingItems, $batchSize);
            
            $offset += $batchSize;
            $io->writeln(sprintf(
                'Processed %d/%d... (Linked: %d, Skipped: %d)',
                $offset,
                $totalItems,
                $result['linked'],
                $result['skipped']
            ));
        }

        $io->success('Process completed');

        return Command::SUCCESS;
    }
} 