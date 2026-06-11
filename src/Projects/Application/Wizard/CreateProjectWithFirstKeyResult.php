<?php

namespace App\Projects\Application\Wizard;


final class CreateProjectWithFirstKeyResult
{
    public function __construct(
        public readonly int $projectId,
        public readonly string $plaintextToken,
    ) {}
}