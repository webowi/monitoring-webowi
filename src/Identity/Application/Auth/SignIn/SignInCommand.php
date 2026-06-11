<?php

namespace App\Identity\Application\Auth\SignIn;

use SensitiveParameter;

final readonly class SignInCommand
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {
    }
}
