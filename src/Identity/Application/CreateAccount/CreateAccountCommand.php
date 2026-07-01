<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateAccount;

use Symfony\Component\Validator\Constraints\Email;

final readonly class CreateAccountCommand
{
    /**
     * @param non-empty-string $email
     */
    public function __construct(
        #[Email]
        public string $email,
        #[\SensitiveParameter]
        public string $plainPassword,
        public string $organizationName,
    ) {}
}
