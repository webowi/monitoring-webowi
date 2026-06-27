<?php

declare(strict_types=1);

namespace App\Logging\Ui\Freshness;

use App\Logging\Application\Freshness\GetProjectFreshnessHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}/freshness', name: 'logging_project_freshness', methods: ['GET'])]
final class ProjectFreshnessController
{
    public function __construct(
        private readonly GetProjectFreshnessHandler $handler,
    ) {}

    public function __invoke(string $projectUuid): JsonResponse
    {
        $lastLogReceivedAt = $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse([
            'lastLogReceivedAt' => $lastLogReceivedAt?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
