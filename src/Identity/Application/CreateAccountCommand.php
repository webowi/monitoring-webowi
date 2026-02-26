<?php

declare(strict_types=1);

namespace App\Identity\Application;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAccountCommand
{
    /**
     * @param non-empty-string $email
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}
