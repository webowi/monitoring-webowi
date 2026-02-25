<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Ui;

use App\Identity\Application\CompanyDataDto;
use App\Identity\Application\CompanyDataProviderInterface;
use App\Identity\Application\Exception\CompanyNotFoundException;
use App\Identity\Ui\Exception\CannotGetGusDataException;
use App\Identity\Ui\GusCompanyDataController;
use App\Identity\Ui\GusDataInput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class GusCompanyDataControllerTest extends TestCase
{
    private MockObject&CompanyDataProviderInterface $companyDataProvider;

    private MockObject&TranslatorInterface $translator;

    private RateLimiterFactory $rateLimiterFactory;

    private MockObject&Request $request;

    private GusCompanyDataController $controller;

    protected function setUp(): void
    {
        $this->companyDataProvider = $this->createMock(CompanyDataProviderInterface::class);
        $this->rateLimiterFactory = new RateLimiterFactory([
            'id'       => 'id',
            'policy'   => 'fixed_window',
            'limit'    => 1,
            'interval' => '60 minutes',
        ], new InMemoryStorage());
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->request = $this->createMock(Request::class);

        $this->controller = new GusCompanyDataController(
            $this->companyDataProvider,
            $this->rateLimiterFactory,
            $this->translator
        );
    }

    #[Test]
    public function throwExceptionWhenTooManyRequests(): void
    {
        $companyDataDto = new CompanyDataDto(
            '123',
            'Company Name',
            '123456789',
            'Province',
            'Street',
            '00-000',
            'City'
        );

        $this->request
            ->expects($this->exactly(2))
            ->method('getClientIp')
            ->willReturn($clientIp = '127.0.0.1');
        $this->rateLimiterFactory->create($clientIp);
        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with('exception.gusApiTooManyRequests')
            ->willReturn($translatedMessage = 'Too many requests');
        $this->companyDataProvider
            ->expects($this->once())
            ->method('getByTin')
            ->with('123')
            ->willReturn($companyDataDto);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->expectExceptionMessage($translatedMessage);

        $this->controller->__invoke(new GusDataInput('123'), $this->request);
        $this->controller->__invoke(new GusDataInput('123'), $this->request);
    }

    #[Test]
    public function throwExceptionWhenCompanyNotFound(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getClientIp')
            ->willReturn(null);
        $this->rateLimiterFactory->create('unknown');
        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with('exception.gusApiNotFound');
        $this->companyDataProvider
            ->expects($this->once())
            ->method('getByTin')
            ->with('123')
            ->willThrowException(new CompanyNotFoundException());

        $this->expectException(NotFoundHttpException::class);

        $this->controller->__invoke(new GusDataInput('123'), $this->request);
    }

    #[Test]
    public function throwExceptionWhenProvidingCompanyDataByTinFails(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getClientIp')
            ->willReturn(null);
        $this->rateLimiterFactory->create('unknown');
        $this->translator
            ->expects($this->never())
            ->method('trans');
        $this->companyDataProvider
            ->expects($this->once())
            ->method('getByTin')
            ->with('123')
            ->willThrowException(new \Exception());

        $this->expectException(CannotGetGusDataException::class);

        $this->controller->__invoke(new GusDataInput('123'), $this->request);
    }

    #[Test]
    public function getCompanyDataByTin(): void
    {
        $companyDataDto = new CompanyDataDto(
            $tin = '123',
            $companyName = 'Company Name',
            $regon = '123456789',
            $province = 'Province',
            $street = 'Street',
            $zipCode = '00-000',
            $city = 'City'
        );

        $this->request
            ->expects($this->once())
            ->method('getClientIp')
            ->willReturn($clientIp = '127.0.0.1');
        $this->rateLimiterFactory->create($clientIp);
        $this->translator
            ->expects($this->never())
            ->method('trans');
        $this->companyDataProvider
            ->expects($this->once())
            ->method('getByTin')
            ->with($tin)
            ->willReturn($companyDataDto);

        $response = $this->controller->__invoke(new GusDataInput($tin), $this->request);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($tin, $data['tin']);
        $this->assertSame($companyName, $data['name']);
        $this->assertSame($regon, $data['regon']);
        $this->assertSame($province, $data['province']);
        $this->assertSame($street, $data['street']);
        $this->assertSame($zipCode, $data['zipCode']);
        $this->assertSame($city, $data['city']);
    }
}
