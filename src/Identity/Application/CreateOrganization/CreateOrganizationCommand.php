<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateOrganization;

final readonly class CreateOrganizationCommand
{
    public function __construct(
        public string $name,
    ) {}
}
