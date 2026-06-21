<?php

declare(strict_types=1);

namespace App\Logging\Application\Ingest;

final class IngestLogEntryMessage
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $occurredAt,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context,
    ) {
    }
}
