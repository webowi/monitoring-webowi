<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure\Security;

use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\Project;
use Symfony\Component\Security\Core\User\UserInterface;

final class IngestionPrincipal implements UserInterface
{
    public function __construct(
        private readonly Project $project,
        private readonly IngestionKey $ingestionKey,
    ) {
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getIngestionKey(): IngestionKey
    {
        return $this->ingestionKey;
    }

    public function getUserIdentifier(): string
    {
        $identifier = (string) $this->project->getUuid();
        \assert('' !== $identifier);

        return $identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_INGESTION'];
    }

    public function eraseCredentials(): void
    {
    }
}
