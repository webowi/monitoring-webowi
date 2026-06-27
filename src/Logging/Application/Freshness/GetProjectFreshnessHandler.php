<?php

declare(strict_types=1);

namespace App\Logging\Application\Freshness;

use App\Kernel\Security\CurrentUserFetcher;
use App\Logging\Application\List\ProjectNotFoundOrAccessDeniedException;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectFreshnessHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
    ) {}

    public function handle(Uuid $projectUuid): ?\DateTimeImmutable
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        return $this->logEntryRepository->getLastReceivedAtByProjectId($projectUuid);
    }
}
