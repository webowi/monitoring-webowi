<?php

namespace App\Projects\Application\Wizard;

use App\Projects\Domain\ProjectPlatformEnum;

final class CreateProjectWithFirstKeyCommand
{
    public function __construct(
        public readonly int $organizationid,
        public readonly string $name,
        public readonly ProjectPlatformEnum $platform,
        public readonly string $keyName = 'prod',
    ) {}
}