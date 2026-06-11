<?php

declare(strict_types=1);

namespace App\Projects\Domain;

use Symfony\Component\Uid\Uuid;

interface ProjectRepositoryInterface
{
    public function countByOrganizationId(Uuid $organizationId): int;

    public function getByOrganizationId(Uuid $organizationId): iterable;

    public function getById(Uuid $projectId,): ?Project;

    public function remove(Project $project): void;
}
