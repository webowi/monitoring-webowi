<?php

declare(strict_types=1);

namespace App\Kernel\Clock;

interface ClockInterface
{
    public function dateTime(\DateTimeImmutable|\DateTime|string|null $input): \DateTimeImmutable;

    public function now(): \DateTimeImmutable;
}
