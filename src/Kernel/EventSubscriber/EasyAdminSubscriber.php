<?php

declare(strict_types=1);

namespace App\Kernel\EventSubscriber;

use App\Kernel\ImageConverter\ImageConverterInterface;
use App\Kernel\ImageConverter\ImageResourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\ImageException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class EasyAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ImageConverterInterface $imageProvider,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, array<string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['provideImagePath'],
            AfterEntityUpdatedEvent::class   => ['updateImagePath'],
        ];
    }

    /**
     * @throws ImageException
     */
    #[NoReturn]
    public function provideImagePath(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof ImageResourceInterface) {
            $this->processConvertImageToWebp($entity);
        }
    }

    /**
     * @throws ImageException
     */
    #[NoReturn]
    public function updateImagePath(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof ImageResourceInterface) {
            $this->processConvertImageToWebp($entity);
        }
    }

    private function processConvertImageToWebp(ImageResourceInterface $entity): void
    {
        try {
            $this->imageProvider->convertImageToWebp($entity);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw new ImageException('Failed to convert image to WebP.');
        }
    }
}
