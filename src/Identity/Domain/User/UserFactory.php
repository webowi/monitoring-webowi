<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\ValueObject\Email;
use Symfony\Component\Uid\Uuid;

class UserFactory
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param non-empty-string $email
     *
     * @throws UserExistException
     */
    public function create(
        Uuid $organizationId,
        string $email,
    ): User {
        if (null !== $this->userRepository->getByEmail(new Email($email))) {
            throw new UserExistException();
        }

        return User::register(
            $organizationId,
            new Email($email),
        );
    }
}
