<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Account\Domain\User\RoleEnum;
use App\Account\Domain\User\User;
use App\Tests\Mock\UserProviderMock;
use Behat\Behat\Context\Context;

class AuthenticationContext implements Context
{
    public function __construct(
        private readonly UserProviderMock $userProvider,
    ) {
    }

    /**
     * @Given I am authorized as :role
     */
    public function authorizeUserWithRole(string $role): void
    {
        $admin = new User();
        $admin->setRoles([$role]);
        $this->userProvider->setUser($admin);
    }

    /**
     * @Given I am authorized admin
     */
    public function authorizeUserAsAdmin(): void
    {
        $this->authorizeUserWithRole(RoleEnum::ADMIN->value);
    }

    /**
     * @Given I am authorized super admin
     */
    public function authorizeUserAsSuperAdmin(): void
    {
        $this->authorizeUserWithRole(RoleEnum::SUPER_ADMIN->value);
    }

    /**
     * @Given I am unauthorized
     */
    public function anonymousUser(): void
    {
        $this->authorizeUserWithRole('unknown');
    }
}
