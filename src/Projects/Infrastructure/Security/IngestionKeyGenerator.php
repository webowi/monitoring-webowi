<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure\Security;

class IngestionKeyGenerator
{
    public function generate(): string
    {
        return 'mon_ing_' . bin2hex(random_bytes(16));
    }
}
