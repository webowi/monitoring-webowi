<?php

declare(strict_types=1);

namespace App\Projects\Application\UpdateProjectSettings;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;

final class UpdateProjectSettingsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
    ) {}

    /**
     * @throws ProjectNameAlreadyExistsException
     * @throws ProjectNotFoundOrAccessDeniedException
     */
    public function handle(UpdateProjectSettingsCommand $command): Project
    {
        $project = $this->projectRepository->getById($command->projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        if (null !== $command->name && $command->name !== $project->name) {
            if ($this->projectRepository->existsByName($command->name)) {
                throw new ProjectNameAlreadyExistsException();
            }

            $project->rename($command->name);
        }

        if (null !== $command->platform) {
            $project->changePlatform($command->platform);
        }

        if (null !== $command->status) {
            $project->changeStatus($command->status);
        }

        $this->projectRepository->save($project);

        return $project;
    }
}
