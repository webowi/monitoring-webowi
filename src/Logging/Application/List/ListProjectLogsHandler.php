<?php

declare(strict_types=1);

namespace App\Logging\Application\List;

use App\Kernel\Security\CurrentUserFetcher;
use App\Logging\Domain\LogEntry;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListProjectLogsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
    ) {}

    /**
     * @return iterable<LogEntry>
     */
    public function handle(Uuid $projectUuid, int $limit, int $offset): iterable
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        return $this->logEntryRepository->getByProjectId($projectUuid, $limit, $offset);
    }
}
