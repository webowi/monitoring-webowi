<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Kernel\Security\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

interface UserRepositoryInterface
{
    public function save(UserInterface $entity): void;

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;

    public function getByEmail(string $email): ?User;
}
