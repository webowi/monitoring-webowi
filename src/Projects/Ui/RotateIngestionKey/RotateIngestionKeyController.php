<?php

declare(strict_types=1);

namespace App\Projects\Ui\RotateIngestionKey;

use App\Projects\Application\RotateIngestionKey\RotateIngestionKeyHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}/ingestion-key/rotate', name: 'projects_rotate_ingestion_key', methods: ['POST'])]
final class RotateIngestionKeyController
{
    public function __construct(
        private readonly RotateIngestionKeyHandler $handler,
    ) {}

    public function __invoke(string $projectUuid): JsonResponse
    {
        $result = $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse([
            'keyUuid' => (string) $result->keyUuid,
            'value' => $result->value,
            'snippet' => $result->snippet,
        ]);
    }
}
