<?php

declare(strict_types=1);

namespace App\Projects\Application\GetProjects;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
    ) {}

    /**
     * @return iterable<Project>
     *
     * @throws ProjectNotFoundOrAccessDeniedException
     */
    public function handle(): iterable
    {
        $user = $this->currentUserFetcher->fetchUser();
        $projects = $this->projectRepository->getByOrganizationId($user->organizationId);

        if (empty($projects) || !$this->isAllProjectBelongToOrganization($projects, $user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        return $this->projectRepository->getByOrganizationId($user->organizationId);
    }

    /**
     * @param iterable<Project> $projects
     */
    private function isAllProjectBelongToOrganization(iterable $projects, Uuid $organizationId): bool
    {
        foreach ($projects as $project) {
            if (!$project->belongsToOrganization($organizationId)) {
                return false;
            }
        }

        return true;
    }
}
