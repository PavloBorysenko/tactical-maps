<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Create Admin User');

        // Get email
        $email = $io->ask('Please enter admin email', null, function ($answer) {
            if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please enter a valid email address');
            }
            return $answer;
        });

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error("User with email '$email' already exists!");
            return Command::FAILURE;
        }

        // Get password
        $password = $io->askHidden('Please enter admin password', function ($answer) {
            if (strlen($answer) < 6) {
                throw new \RuntimeException('Password must be at least 6 characters long');
            }
            return $answer;
        });

        // Confirm password
        $confirmPassword = $io->askHidden('Please confirm admin password');

        if ($password !== $confirmPassword) {
            $io->error('Passwords do not match!');
            return Command::FAILURE;
        }

        // Create admin user
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("Admin user '{$email}' created successfully!");
        $io->info('You can now login at /login');

        return Command::SUCCESS;
    }
} 