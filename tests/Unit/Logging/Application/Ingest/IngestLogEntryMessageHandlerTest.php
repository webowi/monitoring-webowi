<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging\Application\Ingest;

use App\Logging\Application\Ingest\IngestLogEntryMessage;
use App\Logging\Application\Ingest\IngestLogEntryMessageHandler;
use App\Logging\Domain\LogEntry;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Logging\Domain\LogSeverityEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class IngestLogEntryMessageHandlerTest extends TestCase
{
    private MockObject&LogEntryRepositoryInterface $logEntryRepository;

    private IngestLogEntryMessageHandler $handler;

    protected function setUp(): void
    {
        $this->logEntryRepository = $this->createMock(LogEntryRepositoryInterface::class);
        $this->handler = new IngestLogEntryMessageHandler($this->logEntryRepository);
    }

    #[Test]
    public function extractsHttpStatusCodeAndExceptionClassFromContext(): void
    {
        $projectId = Uuid::v4();
        $message = new IngestLogEntryMessage(
            projectId: (string) $projectId,
            occurredAt: '2026-06-21T10:00:00+00:00',
            severity: 'error',
            message: 'boom',
            context: ['http_status_code' => 500, 'exception' => ['class' => 'App\\Some\\Exception']],
        );

        $captured = null;
        $this->logEntryRepository
            ->expects($this->once())
            ->method('add')
            ->with($this->callback(function (LogEntry $logEntry) use (&$captured): bool {
                $captured = $logEntry;

                return true;
            }));

        ($this->handler)($message);

        $this->assertSame(500, $captured->httpStatusCode);
        $this->assertSame('App\\Some\\Exception', $captured->exceptionClass);
        $this->assertSame('boom', $captured->message);
        $this->assertSame(LogSeverityEnum::ERROR, $captured->severity);
        $this->assertTrue($projectId->equals($captured->projectId));
    }

    #[Test]
    public function leavesHttpStatusCodeAndExceptionClassNullWhenMissingFromContext(): void
    {
        $message = new IngestLogEntryMessage(
            projectId: (string) Uuid::v4(),
            occurredAt: '2026-06-21T10:00:00+00:00',
            severity: 'info',
            message: 'no context fields',
            context: [],
        );

        $captured = null;
        $this->logEntryRepository
            ->expects($this->once())
            ->method('add')
            ->with($this->callback(function (LogEntry $logEntry) use (&$captured): bool {
                $captured = $logEntry;

                return true;
            }));

        ($this->handler)($message);

        $this->assertNull($captured->httpStatusCode);
        $this->assertNull($captured->exceptionClass);
    }

    #[Test]
    public function ignoresNonIntegerHttpStatusCode(): void
    {
        $message = new IngestLogEntryMessage(
            projectId: (string) Uuid::v4(),
            occurredAt: '2026-06-21T10:00:00+00:00',
            severity: 'warning',
            message: 'malformed status code',
            context: ['http_status_code' => 'not-a-number'],
        );

        $captured = null;
        $this->logEntryRepository
            ->expects($this->once())
            ->method('add')
            ->with($this->callback(function (LogEntry $logEntry) use (&$captured): bool {
                $captured = $logEntry;

                return true;
            }));

        ($this->handler)($message);

        $this->assertNull($captured->httpStatusCode);
    }

    #[Test]
    public function ignoresMalformedExceptionShape(): void
    {
        $message = new IngestLogEntryMessage(
            projectId: (string) Uuid::v4(),
            occurredAt: '2026-06-21T10:00:00+00:00',
            severity: 'critical',
            message: 'exception not an array',
            context: ['exception' => 'not-an-array'],
        );

        $captured = null;
        $this->logEntryRepository
            ->expects($this->once())
            ->method('add')
            ->with($this->callback(function (LogEntry $logEntry) use (&$captured): bool {
                $captured = $logEntry;

                return true;
            }));

        ($this->handler)($message);

        $this->assertNull($captured->exceptionClass);
    }
}
