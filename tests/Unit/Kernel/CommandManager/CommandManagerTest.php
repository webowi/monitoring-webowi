<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kernel\CommandManager;

use App\Kernel\CommandManager\CommandManager;
use App\Kernel\CommandManager\IoFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CommandManagerTest extends TestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&IoFactoryInterface $ioFactory;

    private MockObject&SymfonyStyle $io;

    private CommandManager $commandManager;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->ioFactory = $this->createMock(IoFactoryInterface::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->commandManager = new CommandManager($this->logger, $this->ioFactory);
    }

    private function testCaseInitialize(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $this->ioFactory
            ->expects($this->once())
            ->method('create')
            ->with($input, $output)
            ->willReturn($this->io);
        $this->commandManager->initialize($input, $output);
    }

    public static function methodDataProvider(): iterable
    {
        yield ['info'];
        yield ['error'];
        yield ['success'];
        yield ['danger'];
    }

    public static function methodInfoDataProvider(): iterable
    {
        yield ['info', 'info', 'info'];
        yield ['error', 'error', 'error'];
        yield ['success', 'info', 'success'];
        yield ['danger', 'error', 'caution'];
    }

    #[DataProvider('methodDataProvider')]
    public function testThrowInitializeIoFirstExceptionForMethod(string $method): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SymfonyStyle is not initialized. Call initialize() method first.');

        $this->commandManager->$method('message');
    }

    #[DataProvider('methodInfoDataProvider')]
    public function testLogMessageAndShowMessageFor(
        string $commandMethod,
        string $loggerMethod,
        string $ioMethod,
    ): void {
        $this->testCaseInitialize();
        $this->io
            ->expects($this->once())
            ->method($ioMethod)
            ->with('message');
        $this->logger
            ->expects($this->once())
            ->method($loggerMethod)
            ->with('message');

        $this->commandManager->$commandMethod('message');
    }
}
