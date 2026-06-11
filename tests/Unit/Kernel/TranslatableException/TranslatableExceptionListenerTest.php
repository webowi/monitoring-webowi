<?php

namespace App\Tests\Unit\Kernel\TranslatableException;

use App\Kernel\TranslatableException\TranslatableExceptionListener;
use App\RealEstate\Domain\RealEstateNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableExceptionListenerTest extends TestCase
{
    private MockObject&TranslatorInterface $translator;

    private TranslatableExceptionListener $exceptionListener;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->exceptionListener = new TranslatableExceptionListener($this->translator);
    }

    public function testTransTranslatableException(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            1,
            new RealEstateNotFoundException()
        );
        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with('error.realEstate.notFound')
            ->willReturn('translated text');

        $this->exceptionListener->__invoke($event);
    }
}
