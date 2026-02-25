<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\CompanyDataProvider;
use App\Identity\Application\Exception\CompanyNotFoundException;
use App\Identity\Application\GusApiClientInterface;
use App\Tests\Unit\Stub\CacheStub;
use GusApi\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CompanyDataProviderTest extends TestCase
{
    private CacheStub $cacheStub;

    private MockObject&GusApiClientInterface $gusApiClient;

    private MockObject&CacheInterface $cache;

    private MockObject&ItemInterface $item;

    private MockObject&LoggerInterface $logger;

    private CompanyDataProvider $companyDataProvider;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cacheStub = new CacheStub($this->cache);
        $this->item = $this->createMock(ItemInterface::class);
        $this->gusApiClient = $this->createMock(GusApiClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->companyDataProvider = new CompanyDataProvider(
            $this->gusApiClient,
            $this->cacheStub,
            $this->logger,
            86400
        );
    }

    #[Test]
    public function throwNotFoundExceptionWhenCompanyDataNotFound(): void
    {
        $tin = '123';
        $exception = new NotFoundException();
        $this->cacheStub->setItem($this->item);

        $this->cacheStub
            ->get(sprintf('gus_data_%s', $tin), function () use ($tin, $exception): void {
                $this->item
                    ->expects($this->once())
                    ->method('expiresAfter')
                    ->with(86400)
                    ->willReturnSelf();
                $this->gusApiClient
                    ->expects($this->once())
                    ->method('fetchByTin')
                    ->with($tin)
                    ->willThrowException($exception);
            });

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Company data not found for TIN', [
                'tin'       => $tin,
                'exception' => $exception,
            ]);

        $this->expectException(CompanyNotFoundException::class);

        $this->companyDataProvider->getByTin($tin);
    }

    #[Test]
    public function throwExceptionWhenFetchingCompanyDataFails(): void
    {
        $tin = '123';
        $exception = new \Exception();
        $this->cacheStub->setItem($this->item);

        $this->cacheStub
            ->get(sprintf('gus_data_%s', $tin), function () use ($tin, $exception): void {
                $this->item
                    ->expects($this->once())
                    ->method('expiresAfter')
                    ->with(86400)
                    ->willReturnSelf();
                $this->gusApiClient
                    ->expects($this->once())
                    ->method('fetchByTin')
                    ->with($tin)
                    ->willThrowException($exception);
            });

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to fetch company data', [
                'tin'       => $tin,
                'exception' => $exception,
            ]);

        $this->expectException(\Exception::class);

        $this->companyDataProvider->getByTin($tin);
    }
}
