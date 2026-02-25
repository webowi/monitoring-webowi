<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface PasswordTokenRepositoryInterface
{
    public function getByToken(string $token, \DateTimeImmutable $now): ?PasswordToken;

    public function save(PasswordToken $passwordToken): void;
}
