<?php

declare(strict_types=1);

namespace App\Projects\Domain;

enum IngestionKeyStatusEnum: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
}
