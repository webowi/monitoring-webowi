<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Account\Domain\User\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProviderMock implements UserProviderInterface
{
    private ?User $user = null;

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new CustomUserMessageAuthenticationException('Forbidden');
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (null === $this->user) {
            throw new \RuntimeException('Access Denied - there is no user');
        }

        return $this->user;
    }
}
