<?php

declare(strict_types=1);

namespace App\Projects\Ui\GetIngestionKey;

use App\Projects\Application\GetIngestionKey\GetIngestionKeyHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}/ingestion-key', name: 'projects_get_ingestion_key', methods: ['GET'])]
final class GetIngestionKeyController
{
    public function __construct(
        private readonly GetIngestionKeyHandler $handler,
    ) {}

    public function __invoke(string $projectUuid): JsonResponse
    {
        $result = $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse([
            'keyUuid' => $result->keyUuid ? (string) $result->keyUuid : null,
            'status' => $result->status,
            'value' => $result->value,
            'snippet' => $result->snippet,
        ]);
    }
}
