<?php

declare(strict_types=1);

namespace App\Projects\Ui\UpdateProjectSettings;

use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\UpdateProjectSettings\UpdateProjectSettingsCommand;
use App\Projects\Application\UpdateProjectSettings\UpdateProjectSettingsHandler;
use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectStatusEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/projects/{projectUuid}', name: 'projects_update_project_settings', methods: ['PATCH'])]
final class UpdateProjectSettingsController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UpdateProjectSettingsHandler $handler,
    ) {}

    /**
     * @throws ProjectNameAlreadyExistsException
     * @throws ProjectNotFoundOrAccessDeniedException
     */
    public function __invoke(string $projectUuid, #[MapRequestPayload] UpdateProjectSettingsInput $input): JsonResponse
    {
        $this->validator->validate($input);

        $project = $this->handler->handle(new UpdateProjectSettingsCommand(
            projectUuid: Uuid::fromString($projectUuid),
            name: $input->name,
            platform: null !== $input->platform ? ProjectPlatformEnum::from($input->platform) : null,
            status: null !== $input->status ? ProjectStatusEnum::from($input->status) : null,
        ));

        return new JsonResponse([
            'uuid' => $project->uuid->toString(),
            'name' => $project->name,
            'platform' => $project->platform->value,
            'status' => $project->status->value,
        ]);
    }
}
