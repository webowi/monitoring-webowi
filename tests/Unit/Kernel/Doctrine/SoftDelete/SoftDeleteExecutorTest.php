<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kernel\Doctrine\SoftDelete;

use App\Kernel\Doctrine\SoftDelete\SoftDeleteExecutor;
use App\Kernel\Doctrine\SoftDelete\SoftDeleteFailedException;
use App\Kernel\Doctrine\SoftDelete\SoftDeleteResourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoftDeleteExecutorTest extends TestCase
{
    private MockObject&EntityManagerInterface $entityManager;

    private MockObject&LoggerInterface        $logger;

    private SoftDeleteExecutor $executor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->executor = new SoftDeleteExecutor($this->entityManager, $this->logger);
    }

    public function testLogAndThrowExceptionWhenDeleteFails(): void
    {
        $entity = new class () implements SoftDeleteResourceInterface {
            private bool $deleted = false;

            public function softDelete(string $deletedBy = 'system'): void
            {
                $this->deleted = true;
            }

            public function isDeleted(): bool
            {
                return $this->deleted;
            }
        };

        $this->entityManager->expects($this->once())->method('flush')
            ->willThrowException($exception = new \Exception());
        $this->logger->expects($this->once())->method('error')
            ->with('Cannot soft delete entity', ['exception' => $exception]);

        $this->expectException(SoftDeleteFailedException::class);

        $this->executor->delete($entity);

        $this->assertTrue($entity->isDeleted());
    }
}
