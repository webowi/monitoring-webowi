<?php

declare(strict_types=1);

namespace App\Projects\Application\RotateIngestionKey;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use App\Projects\Infrastructure\Security\IngestionKeyGenerator;
use App\Projects\Infrastructure\Security\IngestionKeyHasher;
use Symfony\Component\Uid\Uuid;

final class RotateIngestionKeyHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly IngestionKeyRepositoryInterface $ingestionKeyRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
        private readonly IngestionKeyGenerator $keyGenerator,
        private readonly IngestionKeyHasher $keyHasher,
        private readonly InstallSnippetBuilder $snippetBuilder,
    ) {}

    public function handle(Uuid $projectUuid): RotateIngestionKeyResult
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        $existingKey = $this->ingestionKeyRepository->findActiveByProjectId($projectUuid);

        if (null !== $existingKey) {
            $existingKey->revoke();
            $this->ingestionKeyRepository->save($existingKey);
        }

        $plaintext = $this->keyGenerator->generate();
        $hash = $this->keyHasher->hash($plaintext);

        $newKey = (new IngestionKey())
            ->setUuid(Uuid::v4())
            ->setProjectId($projectUuid)
            ->setName($existingKey?->getName() ?? 'Default')
            ->setKeyHash($hash)
            ->setKeyValue($plaintext);

        $this->ingestionKeyRepository->save($newKey);

        return new RotateIngestionKeyResult(
            keyUuid: $newKey->getUuid() ?? throw new \LogicException('New key UUID was not set.'),
            value: $plaintext,
            snippet: $this->snippetBuilder->build($plaintext),
        );
    }
}
