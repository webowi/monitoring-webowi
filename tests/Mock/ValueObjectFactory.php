<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Identity\Domain\ValueObject\Email;

final class ValueObjectFactory
{
    public static function email(string $email): Email
    {
        return new Email($email);
    }

    public static function monitoringEmail(string $localPart): Email
    {
        return new Email($localPart . '@monitoring-webowi.test');
    }
}
