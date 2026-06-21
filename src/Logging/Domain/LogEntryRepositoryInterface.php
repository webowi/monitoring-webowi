<?php

declare(strict_types=1);

namespace App\Logging\Domain;

use Symfony\Component\Uid\Uuid;

interface LogEntryRepositoryInterface
{
    public function add(LogEntry $logEntry): void;

    /**
     * @return iterable<LogEntry>
     */
    public function getByProjectId(Uuid $projectId, int $limit, int $offset): iterable;
}
