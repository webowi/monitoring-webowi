<?php

namespace App\Projects\Application;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteProjectCommand
{
    public function __construct(
        public Uuid $projectId,
        public Uuid $organizationId,
        public ?Uuid $userId = null,
    ) {
    }
}
