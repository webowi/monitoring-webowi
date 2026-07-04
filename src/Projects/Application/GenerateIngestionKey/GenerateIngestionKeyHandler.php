<?php

declare(strict_types=1);

namespace App\Projects\Application\GenerateIngestionKey;

use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\IngestionKeyAlreadyExistsException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use App\Projects\Infrastructure\Security\IngestionKeyGenerator;
use App\Projects\Infrastructure\Security\IngestionKeyHasher;
use Symfony\Component\Uid\Uuid;

final class GenerateIngestionKeyHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly IngestionKeyRepositoryInterface $ingestionKeyRepository,
        private readonly CurrentUserFetcher $currentUserFetcher,
        private readonly IngestionKeyGenerator $keyGenerator,
        private readonly IngestionKeyHasher $keyHasher,
        private readonly InstallSnippetBuilder $snippetBuilder,
    ) {}

    public function handle(Uuid $projectUuid): GenerateIngestionKeyResult
    {
        $project = $this->projectRepository->getById($projectUuid);
        $user = $this->currentUserFetcher->fetchUser();

        if (null === $project || !$project->belongsToOrganization($user->organizationId)) {
            throw new ProjectNotFoundOrAccessDeniedException();
        }

        if (null !== $this->ingestionKeyRepository->findActiveByProjectId($projectUuid)) {
            throw new IngestionKeyAlreadyExistsException();
        }

        $plaintext = $this->keyGenerator->generate();
        $hash = $this->keyHasher->hash($plaintext);

        $newKey = IngestionKey::new(
            $projectUuid,
            null,
            $hash,
            $plaintext
        );

        $this->ingestionKeyRepository->save($newKey);

        return new GenerateIngestionKeyResult(
            keyUuid: $newKey->uuid,
            value: $plaintext,
            snippet: $this->snippetBuilder->build($plaintext),
        );
    }
}
