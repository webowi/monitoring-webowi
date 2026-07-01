<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateOrganization;

use Symfony\Component\Uid\Uuid;

final readonly class CreateOrganizationResult
{
    public function __construct(
        public Uuid $uuid,
    ) {}
}
