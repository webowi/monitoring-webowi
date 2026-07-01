<?php

declare(strict_types=1);

namespace App\Projects\Ui\GetProject;

final readonly class GetProjectOutput
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $platform,
        public string $status,
    ) {}
}
