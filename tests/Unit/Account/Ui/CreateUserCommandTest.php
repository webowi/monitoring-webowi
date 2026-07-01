<?php

declare(strict_types=1);

namespace App\Tests\Unit\Account\Ui;

use App\Account\Application\CreateUserService;
use App\Account\Ui\CreateUserCommand;
use App\Kernel\CommandManager\CommandManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateUserCommandTest extends TestCase
{
    private MockObject&CreateUserService $createUserService;

    private MockObject&CommandManagerInterface $commandManager;

    private CreateUserCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->createUserService = $this->createMock(CreateUserService::class);
        $this->commandManager = $this->createMock(CommandManagerInterface::class);

        $this->command = new CreateUserCommand($this->createUserService, $this->commandManager);
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
        $this->createUserService
            ->expects($this->never())
            ->method('createUser');

        $this->assertSame(1, $this->commandTester->execute([
            '--email'        => 'email@example.com',
            '--force-verify' => true,
        ]));
    }

    public function testFailureWhenCreateUserFailed(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->createUserService
            ->expects($this->once())
            ->method('createUser')
            ->with('email@example.com', 'pass', true, true)
            ->willThrowException(new \Exception('message'));
        $this->commandManager
            ->expects($this->once())
            ->method('error')
            ->with("Creating new admin error: message");

        $this->assertSame(1, $this->commandTester->execute([
            '--email'        => 'email@example.com',
            '--password'     => 'pass',
            '--force-verify' => true,
        ]));
    }

    public function testAddDangerInformationAboutPasswordAfterAddPassword(): void
    {
        $this->commandManager
            ->expects($this->once())
            ->method('initialize');
        $this->createUserService
            ->expects($this->once())
            ->method('createUser')
            ->with('email@example.com', 'pass', true, true);
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
            '--force-verify' => true,
        ]));
    }
}
