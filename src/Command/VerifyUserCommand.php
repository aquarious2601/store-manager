<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:verify-user',
    description: 'Verify user credentials'
)]
class VerifyUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        
        if (!$user) {
            $output->writeln('User not found. Creating new user...');
            $user = new User();
            $user->setUsername('admin');
            $user->setRoles(['ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
            $user->setPassword($hashedPassword);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $output->writeln('User created successfully!');
        } else {
            $output->writeln('User found. Verifying password...');
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
            $user->setPassword($hashedPassword);
            $this->entityManager->flush();
            $output->writeln('Password updated successfully!');
        }

        return Command::SUCCESS;
    }
} 