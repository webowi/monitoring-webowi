<?php

declare(strict_types=1);

namespace App\Tests\Behat\Faker\Provider;

final readonly class TechnicalUserContext
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
