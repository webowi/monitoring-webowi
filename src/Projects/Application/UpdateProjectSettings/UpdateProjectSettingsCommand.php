<?php

declare(strict_types=1);

namespace App\Projects\Application\UpdateProjectSettings;

use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectStatusEnum;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateProjectSettingsCommand
{
    public function __construct(
        public Uuid $projectUuid,
        public ?string $name = null,
        public ?ProjectPlatformEnum $platform = null,
        public ?ProjectStatusEnum $status = null,
    ) {}
}
