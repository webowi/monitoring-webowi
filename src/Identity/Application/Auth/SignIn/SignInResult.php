<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth\SignIn;

final readonly class SignInResult
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public \DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return [
            'access_token'  => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at'    => $this->expiresAt->format('c'),
        ];
    }
}
