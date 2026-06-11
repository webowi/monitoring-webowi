<?php

namespace App\Kernel\TranslatableException;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener]
final class TranslatableExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof TranslatableExceptionInterface) {
            $translatedMessage = $this->translator->trans($exception->getMessage());

            $event->setResponse(
                new JsonResponse(['error' => $translatedMessage], $exception->getCode())
            );
        }
    }
}
