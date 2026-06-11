<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Auth;

use App\Identity\Domain\Auth\RefreshTokenManagerInterface;
use App\Identity\Domain\User\User;

class LexikRefreshTokenManager implements RefreshTokenManagerInterface
{
    public function generate(User $user): string
    {
        return '';
    }
}