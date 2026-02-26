<?php

declare(strict_types=1);

namespace App\Identity\Ui\Command;

use App\Identity\Application\CreateAccountCommand as HandlerCommand;
use App\Identity\Application\CreateAccountHandler;
use App\Kernel\CommandManager\CommandManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create:account',
    description: 'Create new account with default company.',
)]
class CreateAccountCommand extends Command
{
    private const EMAIL = 'email';

    private const PASSWORD = 'password';

    public function __construct(
        private readonly CreateAccountHandler    $createUserService,
        private readonly CommandManagerInterface $commandManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            self::EMAIL,
            'email',
            InputOption::VALUE_REQUIRED,
            'User e-mail.',
        );

        $this->addOption(
            self::PASSWORD,
            'password',
            InputOption::VALUE_REQUIRED,
            'User password.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandManager->initialize($input, $output);
        try {
            $email = $input->getOption(self::EMAIL);
            $password = $input->getOption(self::PASSWORD);


            if (!is_string($email) || !is_string($password)) {
                $this->commandManager->error('Email and password are required.');

                return Command::FAILURE;
            }

            if ('' === $email || '' === $password) {
                $this->commandManager->error('Email and password cannot be empty.');

                return Command::FAILURE;
            }

            $this->createUserService->create(new HandlerCommand($email, $password));

            $this->commandManager->success(sprintf('Creating user %s successful', $email));
            $this->commandManager->danger('Change password for user in panel quickly as possible!');
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->commandManager->error(sprintf('Creating new account error: %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}
