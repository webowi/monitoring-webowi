<?php

declare(strict_types=1);

namespace App\Identity\Domain;

class CompanyFactory
{
    public function create(
        string $companyEmail,
    ): Company {
        return (new Company())
            ->setCompanyEmail($companyEmail);
    }
}
