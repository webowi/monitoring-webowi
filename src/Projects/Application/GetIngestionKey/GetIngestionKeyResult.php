<?php

declare(strict_types=1);

namespace App\Projects\Application\GetIngestionKey;

use Symfony\Component\Uid\Uuid;

final readonly class GetIngestionKeyResult
{
    public function __construct(
        public ?Uuid $keyUuid,
        public string $status,
        public ?string $value,
        public string $snippet,
    ) {}
}
