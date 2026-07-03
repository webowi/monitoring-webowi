<?php

declare(strict_types=1);

namespace App\Projects\Application\CreateProject;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\CannotSaveProjectException;
use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use Psr\Log\LoggerInterface;

final class CreateProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws CannotSaveProjectException
     * @throws ProjectNameAlreadyExistsException
     */
    public function handle(CreateProjectCommand $command): CreateProjectResult
    {
        if ($this->projectRepository->existsByName($command->name)) {
            throw new ProjectNameAlreadyExistsException();
        }

        $user = $this->currentUserFetcher->fetchUser();

        try {
            $project = Project::register(
                organizationId: $user->organizationId,
                name: $command->name,
                platform: $command->platform,
            );

            $this->projectRepository->save($project);

            return new CreateProjectResult(
                uuid: $project->uuid,
                name: $project->name,
                platform: $project->platform,
                status: $project->status,
            );
        } catch (\Throwable $exception) {
            $this->logger->critical(\sprintf('Failed to create project: %s', $exception->getMessage()), [
                'exception' => $exception,
            ]);

            throw new CannotSaveProjectException();
        }

    }
}
