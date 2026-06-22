<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Application\CompanyDataDto;
use App\Identity\Application\GusApiClientInterface;
use GusApi\Exception\NotFoundException;
use GusApi\GusApi;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
final class GusCompanyDataProvider implements GusApiClientInterface
{
    public function __construct(
        private readonly GusApi $gusApi,
    ) {}

    /**
     * @throws NotFoundException
     */
    public function fetchByTin(string $tin): CompanyDataDto
    {
        $this->gusApi->login();
        $searchReport = $this->gusApi->getByNip($tin)[0];

        return new CompanyDataDto(
            $tin,
            $searchReport->getName(),
            $searchReport->getRegon(),
            $searchReport->getProvince(),
            $searchReport->getStreet(),
            $searchReport->getZipCode(),
            empty($searchReport->getCity()) ? $searchReport->getCommunity() : $searchReport->getCity(),
        );
    }
}
