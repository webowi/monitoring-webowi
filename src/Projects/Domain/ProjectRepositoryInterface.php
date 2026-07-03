<?php

declare(strict_types=1);

namespace App\Projects\Domain;

use Symfony\Component\Uid\Uuid;

interface ProjectRepositoryInterface
{
    public function countByOrganizationId(Uuid $organizationId): int;

    /**
     * @return iterable<Project>
     */
    public function getByOrganizationId(Uuid $organizationId): iterable;

    public function getById(Uuid $projectId): ?Project;

    public function existsByName(string $name): bool;

    public function save(Project $project): void;

    public function remove(Project $project): void;
}
