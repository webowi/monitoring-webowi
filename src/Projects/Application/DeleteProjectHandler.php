<?php

namespace App\Projects\Application;

use App\Projects\Domain\ProjectRepositoryInterface;
use Psr\Log\LoggerInterface;

class DeleteProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function delete(DeleteProjectCommand $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);

        if ($project === null) {
            throw new ProjectNotFoundException();
        }

        if (!$project->belongsToOrganization($command->organizationId)) {
            throw new ProjectAccessDeniedException();
        }

        try {
            $this->projectRepository->remove($project);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to delete project. %s', $exception->getMessage()),
                [
                    'exception' => $exception,
                    'projectId' => $project->getUuid(),
                    'organizationId' => $command->organizationId,
                    'userId' => $command->userId,
                ]
            );

            throw new ProjectCannotRemoveException();
        }

        $this->logger
            ->info(
                'Deleted project {projectId}',
                [
                    'projectId' => $command->projectId,
                    'organizationId' => $command->organizationId,
                    'userId' => $command->userId,
                ]
            );
    }
}