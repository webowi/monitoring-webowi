<?php

declare(strict_types=1);

namespace App\Projects\Ui\DeleteProject;

use App\Projects\Application\DeleteProject\DeleteProjectHandler;
use App\Projects\Application\Exception\ProjectCannotRemoveException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/projects/{projectUuid}', name: 'projects_delete_project', methods: ['DELETE'])]
final class DeleteProjectController
{
    public function __construct(
        private readonly DeleteProjectHandler $handler,
    ) {}

    /**
     * @throws ProjectCannotRemoveException
     * @throws ProjectNotFoundOrAccessDeniedException
     */
    public function __invoke(string $projectUuid): JsonResponse
    {
        $this->handler->handle(Uuid::fromString($projectUuid));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
