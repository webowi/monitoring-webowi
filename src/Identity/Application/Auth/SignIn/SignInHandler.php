<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth\SignIn;

use App\Identity\Domain\Auth\RefreshTokenManagerInterface;
use App\Identity\Domain\Auth\TokenGeneratorInterface;
use App\Identity\Domain\User\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Clock\ClockInterface;

class SignInHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws AccountNotVerifiedException
     * @throws InvalidCredentialsException
     * @throws \DateMalformedStringException
     */
    public function handle(SignInCommand $command): SignInResult
    {
        $user = $this->userRepository->getByEmail(new Email($command->email));

        if (null === $user || !$user->verifyPassword($command->password)) {
            throw new InvalidCredentialsException();
        }

        if (!$user->isVerified()) {
            throw new AccountNotVerifiedException();
        }

        $accessToken  = $this->tokenGenerator->generate($user);
        $refreshToken = $this->refreshTokenManager->generate($user);

        return new SignInResult(
            accessToken:  $accessToken,
            refreshToken: $refreshToken,
            expiresAt:    $this->clock->now()->modify('+1 hour'),
        );
    }
}
