<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SymfonyUserAdapter implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly User $domainUser,
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->domainUser->email->email;
    }

    public function getRoles(): array
    {
        return $this->domainUser->getRoles();
    }

    // Lexik wstrzykuje password do JWT payload przez ten interfejs
    public function getPassword(): ?string
    {
        return $this->domainUser->getPassword();
    }

    public function eraseCredentials(): void {}

    public function getDomainUser(): User
    {
        return $this->domainUser;
    }
}
