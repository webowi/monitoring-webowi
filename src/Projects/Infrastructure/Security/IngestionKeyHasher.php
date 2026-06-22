<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure\Security;

final class IngestionKeyHasher
{
    public function __construct(
        private readonly string $appSecret,
    ) {}

    public function hash(string $plaintext): string
    {
        return hash_hmac('sha256', $plaintext, $this->appSecret);
    }
}
