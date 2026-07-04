<?php

declare(strict_types=1);

namespace App\Projects\Application\GenerateIngestionKey;

use Symfony\Component\Uid\Uuid;

final readonly class GenerateIngestionKeyResult
{
    public function __construct(
        public Uuid $keyUuid,
        public string $value,
        public string $snippet,
    ) {}
}
