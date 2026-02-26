<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\CompanyNotFoundException;

interface CompanyDataProviderInterface
{
    /**
     * @throws CompanyNotFoundException
     */
    public function getByTin(string $tin): CompanyDataDto;
}
