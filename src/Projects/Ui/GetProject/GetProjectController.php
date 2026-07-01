<?php

declare(strict_types=1);

namespace App\Projects\Ui\GetProject;

use App\Projects\Application\GetProject\GetProjectHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}', name: 'projects_get_project', methods: ['GET'])]
final class GetProjectController
{
    public function __construct(
        private readonly GetProjectHandler $handler,
    ) {}

    public function __invoke(string $projectUuid): JsonResponse
    {
        $project = $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse([
            'uuid' => $project->uuid->toString(),
            'name' => $project->name,
            'platform' => $project->platform->value,
            'status' => $project->status->value,
        ]);
    }
}
