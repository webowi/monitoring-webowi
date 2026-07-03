<?php

declare(strict_types=1);

namespace App\Projects\Application\CreateProject;

use App\Projects\Domain\ProjectPlatformEnum;

final readonly class CreateProjectCommand
{
    public function __construct(
        public string $name,
        public ProjectPlatformEnum $platform,
    ) {}
}
