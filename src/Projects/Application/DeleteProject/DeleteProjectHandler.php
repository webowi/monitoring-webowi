<?php

declare(strict_types=1);

namespace App\Projects\Application\DeleteProject;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectCannotRemoveException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly IngestionKeyRepositoryInterface $ingestionKeyRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ProjectCannotRemoveException
     * @throws ProjectNotFoundOrAccessDeniedException
     */
    public function handle(Uuid $projectUuid): void
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        try {
            $this->ingestionKeyRepository->removeAllByProjectId($projectUuid);
            $this->projectRepository->remove($project);
        } catch (\Throwable $exception) {
            $this->logger->critical(
                \sprintf('Failed to delete project: %s', $exception->getMessage()),
                [
                    'exception' => $exception,
                ]
            );

            throw new ProjectCannotRemoveException();
        }

        $this->logger->info('Deleted project {projectId}', [
            'projectId' => $projectUuid->toString(),
        ]);
    }
}
