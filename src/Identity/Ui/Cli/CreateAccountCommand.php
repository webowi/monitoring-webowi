<?php

declare(strict_types=1);

namespace App\Identity\Ui\Cli;

use App\Identity\Application\CreateAccount\CreateAccountCommand as CreateAccountDto;
use App\Identity\Application\CreateAccount\CreateAccountHandler;
use App\Identity\Domain\User\UserExistException;
use App\Kernel\CommandManager\CommandManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mw:create:account',
    description: 'Creates a new user account with an organization',
)]
class CreateAccountCommand extends Command
{
    public function __construct(
        private readonly CreateAccountHandler $handler,
        private readonly CommandManagerInterface $commandManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Administrator email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Administrator password')
            ->addOption('organization-name', null, InputOption::VALUE_OPTIONAL, 'Organization name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandManager->initialize($input, $output);

        $email = $input->getOption('email')
            ?: $this->commandManager->ask('Email address');

        $plainPassword = $input->getOption('password')
            ?: $this->commandManager->askHidden('Password');

        $organizationName = $input->getOption('organization-name') ?: 'Default Organization';

        if (!\is_string($email) || '' === $email) {
            $this->commandManager->error('Email is required.');

            return Command::FAILURE;
        }

        if (!\is_string($plainPassword) || '' === $plainPassword) {
            $this->commandManager->error('Password is required.');

            return Command::FAILURE;
        }

        if (!\is_string($organizationName)) {
            $this->commandManager->error('Organization name is required.');

            return Command::FAILURE;
        }

        try {
            $this->handler->handle(new CreateAccountDto(
                email: $email,
                plainPassword: $plainPassword,
                organizationName: $organizationName,
            ));
        } catch (UserExistException|\InvalidArgumentException $e) {
            $this->commandManager->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->commandManager->success(\sprintf('Account created for %s', $email));

        return Command::SUCCESS;
    }
}
