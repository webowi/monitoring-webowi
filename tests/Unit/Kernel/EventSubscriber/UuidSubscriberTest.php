<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kernel\EventSubscriber;

use App\Kernel\EventSubscriber\UuidResourceInterface;
use App\Kernel\EventSubscriber\UuidSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UuidSubscriberTest extends TestCase
{
    private MockObject&LifecycleEventArgs $args;

    private UuidSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->args = $this->createMock(LifecycleEventArgs::class);
        $this->subscriber = new UuidSubscriber();
    }

    public function testNoSubscribeWhenNoIdResource(): void
    {
        $this->args->expects($this->once())
            ->method('getObject')
            ->willReturn(new class () {});

        $this->subscriber->prePersist($this->args);
    }

    public function testSetIdWhenIdResourceWithoudId(): void
    {
        $entity = new class () implements UuidResourceInterface {
            private ?Uuid $id = null;

            public function getUuid(): ?Uuid
            {
                return $this->id;
            }

            public function setUuid(?Uuid $uuid): self
            {
                $this->id = $uuid;

                return $this;
            }
        };

        $this->args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);

        $this->subscriber->prePersist($this->args);

        $this->assertNotNull($entity->getUuid());
    }
}
