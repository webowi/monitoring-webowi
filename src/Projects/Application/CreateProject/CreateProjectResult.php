<?php

declare(strict_types=1);

namespace App\Projects\Application\CreateProject;

use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectStatusEnum;
use Symfony\Component\Uid\Uuid;

final readonly class CreateProjectResult
{
    public function __construct(
        public Uuid $uuid,
        public string $name,
        public ProjectPlatformEnum $platform,
        public ProjectStatusEnum $status,
    ) {}
}
