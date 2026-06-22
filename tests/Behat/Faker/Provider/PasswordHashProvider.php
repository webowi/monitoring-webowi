<?php

declare(strict_types=1);

namespace App\Tests\Behat\Faker\Provider;

use App\Account\Domain\User\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

class PasswordHashProvider
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {}

    public function hashPassword(User $user, string $plainPassword): string
    {
        return $this->passwordHasherFactory->getPasswordHasher($user)->hash($plainPassword);
    }
}
