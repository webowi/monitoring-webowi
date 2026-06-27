<?php

declare(strict_types=1);

namespace App\Projects\Application\RotateIngestionKey;

use Symfony\Component\Uid\Uuid;

final readonly class RotateIngestionKeyResult
{
    public function __construct(
        public Uuid $keyUuid,
        public string $value,
        public string $snippet,
    ) {}
}
