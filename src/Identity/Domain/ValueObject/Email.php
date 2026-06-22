<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping as ORM;

#[Embeddable]
final class Email
{
    /** @var non-empty-string */
    #[ORM\Column(type: Types::STRING, length: 180, unique: true, nullable: false)]
    public readonly string $email;

    public function __construct(string $email)
    {
        $normalized = strtolower(trim($email));
        if ('' === $normalized || !filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: $normalized");
        }

        /* @var non-empty-string $normalized */
        $this->email = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->email === $other->email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
