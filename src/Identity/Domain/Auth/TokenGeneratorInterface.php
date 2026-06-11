<?php

namespace App\Identity\Domain\Auth;

use App\Identity\Domain\User\User;

interface TokenGeneratorInterface
{
    public function generate(User $user): string;
}
