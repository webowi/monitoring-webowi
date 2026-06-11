<?php

namespace App\Identity\Domain\User;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNVERIFIED = 'unverified';
}