<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Ui\Command;

use App\Identity\Application\CreateAccountCommand as HandlerCommand;
use App\Identity\Application\CreateAccountHandler;
use App\Identity\Ui\Command\CreateAccountCommand;
use App\Kernel\CommandManager\CommandManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateAccountCommandTest extends TestCase
{
    private MockObject&CreateAccountHandler $createAccountHandler;

    private MockObject&CommandManagerInterface $commandManager;

    private CreateAccountCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->createAccountHandler = $this->createMock(CreateAccountHandler::class);
        $this->commandManager = $this->createMock(CommandManagerInterface::class);

        $this->command = new CreateAccountCommand($this->createAccountHandler, $this->commandManager);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testFailureWhenPasswordNotProvided(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->commandManager
            ->expects($this->once())
            ->method('error')
            ->with("Email and password are required.");
        $this->createAccountHandler
            ->expects($this->never())
            ->method('create');

        $this->assertSame(1, $this->commandTester->execute([
            '--email'        => 'email@example.com',
        ]));
    }

    public function testFailureWhenPasswordEmptyStringProvided(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->commandManager
            ->expects($this->once())
            ->method('error')
            ->with("Email and password cannot be empty.");
        $this->createAccountHandler
            ->expects($this->never())
            ->method('create');

        $this->assertSame(1, $this->commandTester->execute([
            '--email'           => 'email@example.com',
            '--password'        => '',
        ]));
    }

    public function testFailureWhenCreateUserFailed(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->createAccountHandler
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(
                static fn (HandlerCommand $command) =>
                'email@example.com' === $command->email
                && 'pass' === $command->password
            ))
            ->willThrowException(new \Exception('message'));
        $this->commandManager
            ->expects($this->once())
            ->method('error')
            ->with("Creating new account error: message");

        $this->assertSame(1, $this->commandTester->execute([
            '--email'        => 'email@example.com',
            '--password'     => 'pass',
        ]));
    }

    public function testAddDangerInformationAboutPasswordAfterAddPassword(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->createAccountHandler
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(
                static fn (HandlerCommand $command) =>
                'email@example.com' === $command->email
                && 'pass' === $command->password
            ));
        $this->commandManager
            ->expects($this->once())
            ->method('success')
            ->with('Creating user email@example.com successful');
        $this->commandManager
            ->expects($this->once())
            ->method('danger')
            ->with('Change password for user in panel quickly as possible!');
        $this->commandManager
            ->expects($this->never())
            ->method('error');

        $this->assertSame(0, $this->commandTester->execute([
            '--email'        => 'email@example.com',
            '--password'     => 'pass',
        ]));
    }
}
