<?php

namespace App\Projects\Application\Wizard;

use App\Projects\Domain\ProjectPlatformEnum;

final class WizardState
{
    public function __construct(
        public ?string $name = null,
        public ?ProjectPlatformEnum $platform = null,
        public ?int $projectId = null, // po utworzeniu
    ) {}
}