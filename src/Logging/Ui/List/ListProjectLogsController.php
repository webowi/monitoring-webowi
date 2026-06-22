<?php

declare(strict_types=1);

namespace App\Logging\Ui\List;

use App\Logging\Application\List\ListProjectLogsHandler;
use App\Logging\Domain\LogEntry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}/logs', name: 'logging_list_project_logs', methods: ['GET'])]
final class ListProjectLogsController
{
    public function __construct(
        private readonly ListProjectLogsHandler $handler,
    ) {}

    public function __invoke(
        string $projectUuid,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListProjectLogsInput $input,
    ): JsonResponse {
        $httpStatusCodeRange = $input->httpStatusCodeRange();

        $logEntries = $this->handler->handle(
            Uuid::fromString($projectUuid),
            $input->limit,
            $input->offset,
            $input->severities(),
            $httpStatusCodeRange[0] ?? null,
            $httpStatusCodeRange[1] ?? null,
        );

        $rows = [];
        foreach ($logEntries as $logEntry) {
            $rows[] = $this->toRow($logEntry);
        }

        return new JsonResponse($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(LogEntry $logEntry): array
    {
        return [
            'occurredAt' => $logEntry->occurredAt->format(\DateTimeInterface::ATOM),
            'severity' => $logEntry->severity->value,
            'message' => $logEntry->message,
            'httpStatusCode' => $logEntry->httpStatusCode,
            'exceptionClass' => $logEntry->exceptionClass,
            'context' => $logEntry->context,
        ];
    }
}
