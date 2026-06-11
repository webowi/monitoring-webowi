<?php

declare(strict_types=1);

namespace App\Identity\Domain\Organization;

use Symfony\Component\Uid\Uuid;

interface OrganizationRepositoryInterface
{
    public function save(Organization $organization): void;

    public function getById(Uuid $organizationId): ?Organization;
}
