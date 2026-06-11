<?php

declare(strict_types=1);

namespace App\Kernel\Clock;

class Clock implements ClockInterface
{
    private \DateTimeZone $timezone;

    public function __construct(string $systemTimezone = 'Europe/Warsaw')
    {
        $this->timezone = new \DateTimeZone($systemTimezone);
    }

    public function dateTime(\DateTimeImmutable|\DateTime|string|null $input): \DateTimeImmutable
    {
        if (null === $input) {
            return $this->now();
        }

        if (\is_string($input)) {
            return new \DateTimeImmutable($input, $this->timezone)->setTimezone($this->timezone);
        }

        if ($input instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($input)->setTimezone($this->timezone);
        }

        return $input->setTimezone($this->timezone);
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }
}
