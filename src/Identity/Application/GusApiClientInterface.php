<?php

declare(strict_types=1);

namespace App\Identity\Application;

use GusApi\Exception\NotFoundException;

interface GusApiClientInterface
{
    /**
     * @throws NotFoundException
     */
    public function fetchByTin(string $tin): CompanyDataDto;
}
