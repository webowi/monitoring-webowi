<?php

declare(strict_types=1);

namespace App\Projects\Ui\GenerateIngestionKey;

use App\Projects\Application\GenerateIngestionKey\GenerateIngestionKeyHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}/ingestion-key', name: 'projects_generate_ingestion_key', methods: ['POST'])]
final class GenerateIngestionKeyController
{
    public function __construct(
        private readonly GenerateIngestionKeyHandler $handler,
    ) {}

    public function __invoke(string $projectUuid): JsonResponse
    {
        $result = $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse([
            'keyUuid' => (string) $result->keyUuid,
            'value' => $result->value,
            'snippet' => $result->snippet,
        ], Response::HTTP_CREATED);
    }
}
