<?php

namespace App\Identity\Domain\Auth;

use App\Identity\Domain\User\User;

interface RefreshTokenManagerInterface
{
    public function generate(User $user): string;
}