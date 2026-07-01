<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Ui\Cli;

use App\Identity\Application\CreateAccount\CreateAccountHandler;
use App\Identity\Domain\User\UserExistException;
use App\Identity\Ui\Cli\CreateAccountCommand;
use App\Kernel\CommandManager\CommandManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateAccountCommandTest extends TestCase
{
    private MockObject&CreateAccountHandler $handler;

    private MockObject&CommandManagerInterface $commandManager;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->handler        = $this->createMock(CreateAccountHandler::class);
        $this->commandManager = $this->createMock(CommandManagerInterface::class);
        $this->tester         = new CommandTester(new CreateAccountCommand($this->handler, $this->commandManager));
    }

    private function stubInputs(string $email, ?string $password, ?string $orgName = 'Acme Corp'): void
    {
        $this->commandManager
            ->method('ask')
            ->willReturnOnConsecutiveCalls($email, $orgName);

        $this->commandManager
            ->method('askHidden')
            ->willReturn($password);
    }

    #[Test]
    public function returnsSuccessOnValidInput(): void
    {
        $this->stubInputs('user@example.com', 'secret123');
        $this->handler->expects($this->once())->method('handle');
        $this->commandManager->expects($this->once())->method('success');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function returnsFailureWhenEmailIsEmpty(): void
    {
        $this->stubInputs('', 'secret123');
        $this->handler->expects($this->never())->method('handle');
        $this->commandManager->expects($this->once())->method('error');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function returnsFailureWhenEmailIsNull(): void
    {
        $this->commandManager->method('ask')->willReturn(null);
        $this->commandManager->method('askHidden')->willReturn('secret123');
        $this->handler->expects($this->never())->method('handle');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function returnsFailureWhenPasswordIsEmpty(): void
    {
        $this->stubInputs('user@example.com', '');
        $this->handler->expects($this->never())->method('handle');
        $this->commandManager->expects($this->once())->method('error');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function returnsFailureWhenPasswordIsNull(): void
    {
        $this->stubInputs('user@example.com', null);
        $this->handler->expects($this->never())->method('handle');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function returnsFailureWhenUserAlreadyExists(): void
    {
        $this->stubInputs('user@example.com', 'secret123');
        $this->handler->method('handle')->willThrowException(new UserExistException());
        $this->commandManager->expects($this->once())->method('error');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function returnsFailureOnInvalidEmailFormat(): void
    {
        $this->stubInputs('not-an-email', 'secret123');
        $this->handler->method('handle')->willThrowException(new \InvalidArgumentException('Invalid email'));
        $this->commandManager->expects($this->once())->method('error');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function usesDefaultOrganizationNameWhenOrgInputIsNull(): void
    {
        $this->commandManager
            ->method('ask')
            ->willReturnOnConsecutiveCalls('user@example.com', null);
        $this->commandManager->method('askHidden')->willReturn('secret123');

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(
                fn ($cmd) => 'Default Organization' === $cmd->organizationName,
            ));

        $this->tester->execute([]);
    }
}
