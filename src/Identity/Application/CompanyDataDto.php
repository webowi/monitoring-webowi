<?php

declare(strict_types=1);

namespace App\Identity\Application;

final readonly class CompanyDataDto
{
    public function __construct(
        public string $tin,
        public string $name,
        public string $regon,
        public string $province,
        public string $street,
        public string $zipCode,
        public string $city,
    ) {
    }
}
