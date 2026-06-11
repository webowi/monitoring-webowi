<?php

namespace App\Kernel\Security;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\User\User;
use App\Identity\Infrastructure\Db\OrganizationRepository;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserFetcher
{
    public function __construct(
        private readonly Security $security,
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    public function fetchUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('Current user is not an instance of User.');
        }

        return $user;
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