<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\Email;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SymfonyUserAdapter>
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function loadUserByIdentifier(string $identifier): SymfonyUserAdapter
    {
        $user = $this->userRepository->getByEmail(new Email($identifier));

        if (null === $user) {
            throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
        }

        return new SymfonyUserAdapter($user);
    }

    public function refreshUser(UserInterface $user): SymfonyUserAdapter
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, SymfonyUserAdapter::class, true);
    }
}
