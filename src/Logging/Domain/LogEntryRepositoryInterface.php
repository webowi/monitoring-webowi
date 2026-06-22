<?php

declare(strict_types=1);

namespace App\Logging\Domain;

use Symfony\Component\Uid\Uuid;

interface LogEntryRepositoryInterface
{
    public function add(LogEntry $logEntry): void;

    /**
     * @param LogSeverityEnum[] $severities
     *
     * @return iterable<LogEntry>
     */
    public function getByProjectId(
        Uuid $projectId,
        int $limit,
        int $offset,
        array $severities = [],
        ?int $httpStatusCodeMin = null,
        ?int $httpStatusCodeMax = null,
    ): iterable;
}
