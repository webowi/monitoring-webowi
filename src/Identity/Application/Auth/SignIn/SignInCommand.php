<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth\SignIn;

final readonly class SignInCommand
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
