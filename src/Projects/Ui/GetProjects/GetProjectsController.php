<?php

declare(strict_types=1);

namespace App\Projects\Ui\GetProjects;

use App\Projects\Application\GetProjects\GetProjectsHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/projects', name: 'projects_get_projects', methods: ['GET'])]
final class GetProjectsController
{
    public function __construct(
        private readonly GetProjectsHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $items = [];

        foreach ($this->handler->handle() as $project) {
            $items[] = [
                'uuid' => (string) $project->uuid,
                'name' => $project->name,
                'platform' => $project->platform->value,
                'status' => $project->status->value,
            ];
        }

        return new JsonResponse($items);
    }
}
