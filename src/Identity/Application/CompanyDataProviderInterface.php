<?php

declare(strict_types=1);

namespace App\Identity\Application;

use GusApi\Exception\NotFoundException;

interface CompanyDataProviderInterface
{
    /**
     * @throws NotFoundException
     */
    public function getByTin(string $tin): CompanyDataDto;
}
