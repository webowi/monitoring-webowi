<?php

declare(strict_types=1);

namespace App\Logging\Application\Ingest;

use App\Logging\Domain\LogEntry;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Logging\Domain\LogSeverityEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class IngestLogEntryMessageHandler
{
    public function __construct(
        private readonly LogEntryRepositoryInterface $logEntryRepository,
    ) {}

    public function __invoke(IngestLogEntryMessage $message): void
    {
        $logEntry = LogEntry::create(
            projectId: Uuid::fromString($message->projectId),
            occurredAt: new \DateTimeImmutable($message->occurredAt),
            severity: LogSeverityEnum::from($message->severity),
            message: $message->message,
            httpStatusCode: $this->extractHttpStatusCode($message->context),
            exceptionClass: $this->extractExceptionClass($message->context),
            context: $message->context,
        );

        $this->logEntryRepository->add($logEntry);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractHttpStatusCode(array $context): ?int
    {
        $value = $context['http_status_code'] ?? null;

        return \is_int($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractExceptionClass(array $context): ?string
    {
        $exception = $context['exception'] ?? null;

        if (!\is_array($exception)) {
            return null;
        }

        $class = $exception['class'] ?? null;

        return \is_string($class) ? $class : null;
    }
}
