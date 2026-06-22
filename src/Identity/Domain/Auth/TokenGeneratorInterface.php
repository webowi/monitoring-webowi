<?php

declare(strict_types=1);

namespace App\Identity\Domain\Auth;

use App\Identity\Domain\User\User;

interface TokenGeneratorInterface
{
    public function generate(User $user): string;
}
