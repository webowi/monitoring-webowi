<?php

declare(strict_types=1);

namespace App\Projects\Ui\CreateProject;

use App\Projects\Application\CreateProject\CreateProjectCommand;
use App\Projects\Application\CreateProject\CreateProjectHandler;
use App\Projects\Application\Exception\CannotSaveProjectException;
use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Domain\ProjectPlatformEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/projects', name: 'projects_post_project', methods: ['POST'])]
final class CreateProjectController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CreateProjectHandler $handler,
    ) {}

    /**
     * @throws CannotSaveProjectException
     * @throws ProjectNameAlreadyExistsException
     */
    public function __invoke(#[MapRequestPayload] CreateProjectInput $input): JsonResponse
    {
        $this->validator->validate($input);

        $result = $this->handler->handle(new CreateProjectCommand(
            name: $input->name,
            platform: ProjectPlatformEnum::from($input->platform),
        ));

        return new JsonResponse([
            'uuid' => $result->uuid->toString(),
            'name' => $result->name,
            'platform' => $result->platform->value,
            'status' => $result->status->value,
        ], Response::HTTP_CREATED);
    }
}
