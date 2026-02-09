<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kernel\EventSubscriber;

use App\Kernel\EventSubscriber\EasyAdminSubscriber;
use App\Kernel\ImageConverter\ImageConverterInterface;
use App\Kernel\ImageConverter\ImageResourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\ImageException;
use Symfony\Component\HttpFoundation\File\File;

class EasyAdminSubscriberTest extends TestCase
{
    private MockObject&ImageConverterInterface $imageConverter;

    private MockObject&EntityManagerInterface $entityManager;

    private MockObject&LoggerInterface $logger;

    private EasyAdminSubscriber $easyAdminSubscriber;

    protected function setUp(): void
    {
        $this->imageConverter = $this->createMock(ImageConverterInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->easyAdminSubscriber = new EasyAdminSubscriber(
            $this->imageConverter,
            $this->entityManager,
            $this->logger
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = EasyAdminSubscriber::getSubscribedEvents();

        $this->assertSame($subscribedEvents[AfterEntityPersistedEvent::class][0], 'provideImagePath');
        $this->assertSame($subscribedEvents[AfterEntityUpdatedEvent::class][0], 'updateImagePath');
    }

    /**
     * @return array<int, string[]>
     */
    public static function providerForConvertImageMethods(): array
    {
        $entity = new class () implements ImageResourceInterface {
            public function getImagePath(): ?string
            {
                return null;
            }

            public function setImagePath(?string $imagePath): self
            {
                return $this;
            }

            public function setImageFile(?File $imageFile = null): void
            {

            }

            public function setImageName(?string $imageName): self
            {
                return $this;
            }
        };

        return [
            [$entity, new AfterEntityPersistedEvent($entity), 'provideImagePath'],
            [$entity, new AfterEntityUpdatedEvent($entity), 'updateImagePath'],
        ];
    }

    #[DataProvider('providerForConvertImageMethods')]
    public function testThrowExceptionAndLogErrorWhenConvertImageFails(
        ImageResourceInterface $entity,
        AbstractLifecycleEvent $event,
        string $methodName
    ): void {

        $this->imageConverter
            ->expects($this->once())
            ->method('convertImageToWebp')
            ->with($entity);
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($entity);
        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException($exception = new \Exception('An error occurred'));
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with($exception->getMessage());

        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('Failed to convert image to WebP.');

        $this->easyAdminSubscriber->{$methodName}($event);
    }
}
