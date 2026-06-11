<?php

namespace App\Kernel\Clock;

interface ClockInterface
{
    public function dateTime(\DateTimeImmutable|\DateTime|string|null $input): \DateTimeImmutable;

    public function now(): \DateTimeImmutable;
}
