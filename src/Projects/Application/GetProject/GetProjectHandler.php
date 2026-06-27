<?php

declare(strict_types=1);

namespace App\Projects\Application\GetProject;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
    ) {}

    public function handle(Uuid $projectUuid): Project
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        return $project;
    }
}
