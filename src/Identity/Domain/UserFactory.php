<?php

declare(strict_types=1);

namespace App\Account\Domain;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws UserExistException
     */
    public function create(string $email, string $password): User
    {
        if (null !== $this->userRepository->getByEmail($email)) {
            throw new UserExistException();
        }
        $user = new User();
        $user->setEmail($email);
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $password
        );
        $user->setPassword($hashedPassword);
        $user->makeAdmin();

        return $user;
    }
}
