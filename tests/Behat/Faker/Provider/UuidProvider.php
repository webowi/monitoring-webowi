<?php

declare(strict_types=1);

namespace App\Tests\Behat\Faker\Provider;

use Faker\Provider\Uuid;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

final class UuidProvider extends Uuid
{
    public static function uuid(?string $fromString = null): SymfonyUuid
    {
        if (null === $fromString) {
            return SymfonyUuid::v4();
        }

        return SymfonyUuid::fromString($fromString);
    }
}
