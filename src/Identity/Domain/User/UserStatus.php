<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNVERIFIED = 'unverified';
}
