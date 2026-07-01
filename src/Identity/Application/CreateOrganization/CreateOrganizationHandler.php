<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateOrganization;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\Organization\OrganizationRepositoryInterface;

class CreateOrganizationHandler
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function handle(CreateOrganizationCommand $command): CreateOrganizationResult
    {
        $organization = Organization::register($command->name);
        $this->organizationRepository->save($organization);

        return new CreateOrganizationResult($organization->uuid);
    }
}
