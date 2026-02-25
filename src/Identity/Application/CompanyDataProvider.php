<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\CompanyNotFoundException;
use GusApi\Exception\NotFoundException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CompanyDataProvider implements CompanyDataProviderInterface
{
    public function __construct(
        private readonly GusApiClientInterface $gusApiClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CACHE_EXPIRATION_TIME)%')]
        private readonly int $cacheExpirationTime,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws CompanyNotFoundException
     * @throws InvalidArgumentException
     */
    public function getByTin(string $tin): CompanyDataDto
    {
        try {
            return $this->cache->get(
                sprintf('gus_company_data_%s', $tin),
                function (ItemInterface $item) use ($tin) {
                    $item->expiresAfter($this->cacheExpirationTime);
                    return $this->gusApiClient->fetchByTin($tin);
                }
            );
        } catch (NotFoundException $exception) {
            $this->logger->warning('Company data not found for TIN', [
                'tin'       => $tin,
                'exception' => $exception,
            ]);

            throw new CompanyNotFoundException();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch company data', [
                'tin'       => $tin,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
