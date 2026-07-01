<?php

declare(strict_types=1);

namespace App\Account\Ui;

use App\Account\Application\CreateUserService;
use App\Kernel\CommandManager\CommandManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create:user',
    description: 'Create new user.',
)]
class CreateUserCommand extends Command
{
    private const EMAIL = 'email';

    private const PASSWORD = 'password';

    private const FORCE_VERIFY = 'force-verify';

    public function __construct(
        private readonly CreateUserService $createUserService,
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

        $this->addOption(
            self::FORCE_VERIFY,
            'fv',
            InputOption::VALUE_NONE,
            'Force verify user.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandManager->initialize($input, $output);
        try {
            $email = $input->getOption(self::EMAIL);
            $password = $input->getOption(self::PASSWORD);
            $forceVerify = $input->getOption(self::FORCE_VERIFY);


            if (null === $email || null === $password) {
                $this->commandManager->error('Email and password are required.');

                return Command::FAILURE;
            }

            $this->createUserService->createUser($email, $password, forceVerify: $forceVerify);

            $this->commandManager->success(sprintf('Creating user %s successful', $email));
            $this->commandManager->danger('Change password for user in panel quickly as possible!');
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->commandManager->error(sprintf('Creating new admin error: %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}
