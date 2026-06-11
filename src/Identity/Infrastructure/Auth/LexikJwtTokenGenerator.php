<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Auth;

use App\Identity\Domain\Auth\TokenGeneratorInterface;
use App\Identity\Domain\User\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class LexikJwtTokenGenerator implements TokenGeneratorInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function generate(User $user): string
    {
        return $this->jwtManager->create($user);
    }
}
