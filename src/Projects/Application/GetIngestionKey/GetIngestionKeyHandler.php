<?php

declare(strict_types=1);

namespace App\Projects\Application\GetIngestionKey;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetIngestionKeyHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly IngestionKeyRepositoryInterface $ingestionKeyRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
        private readonly InstallSnippetBuilder $snippetBuilder,
    ) {}

    public function handle(Uuid $projectUuid): GetIngestionKeyResult
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        $key = $this->ingestionKeyRepository->findActiveByProjectId($projectUuid);

        return new GetIngestionKeyResult(
            keyUuid: $key?->getUuid(),
            status: $key?->getStatus()->value ?? 'none',
            value: $key?->getKeyValue(),
            snippet: $this->snippetBuilder->build($key?->getKeyValue() ?? ''),
        );
    }
}
