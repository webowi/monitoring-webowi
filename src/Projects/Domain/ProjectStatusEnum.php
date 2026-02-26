<?php

declare(strict_types=1);

namespace App\Projects\Domain;

enum ProjectStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
