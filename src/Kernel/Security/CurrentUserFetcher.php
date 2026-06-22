<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\Organization\OrganizationRepositoryInterface;
use App\Identity\Domain\User\User;
use App\Identity\Infrastructure\Security\SymfonyUserAdapter;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserFetcher
{
    public function __construct(
        private readonly Security $security,
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function fetchUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof SymfonyUserAdapter) {
            throw new \LogicException('Current user is not an instance of User.');
        }

        return $user->getDomainUser();
    }

    public function fetchUserOrganization(): Organization
    {
        $user = $this->fetchUser();
        $organization = $this->organizationRepository->getById($user->organizationId);

        if (!$organization instanceof Organization) {
            throw new \LogicException('Current user does not have an associated organization.');
        }

        return $organization;
    }
}
