<?php

declare(strict_types=1);

namespace App\Identity\Domain\Auth;

use App\Identity\Domain\User\User;

interface RefreshTokenManagerInterface
{
    public function generate(User $user): string;
}
