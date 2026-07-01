<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\CreateOrganization;

use App\Identity\Application\CreateOrganization\CreateOrganizationCommand;
use App\Identity\Application\CreateOrganization\CreateOrganizationHandler;
use App\Identity\Application\CreateOrganization\CreateOrganizationResult;
use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\Organization\OrganizationRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CreateOrganizationHandlerTest extends TestCase
{
    private MockObject&OrganizationRepositoryInterface $organizationRepository;

    private CreateOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->organizationRepository = $this->createMock(OrganizationRepositoryInterface::class);
        $this->handler = new CreateOrganizationHandler($this->organizationRepository);
    }

    #[Test]
    public function returnsResultWithUuidOnSuccess(): void
    {
        $this->organizationRepository->expects($this->once())->method('save');

        $result = $this->handler->handle(new CreateOrganizationCommand('Acme Corp'));

        $this->assertInstanceOf(CreateOrganizationResult::class, $result);
        $this->assertInstanceOf(Uuid::class, $result->uuid);
    }

    #[Test]
    public function savesOrganizationWithGivenName(): void
    {
        $this->organizationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Organization $org) => 'Acme Corp' === $org->name,
            ));

        $this->handler->handle(new CreateOrganizationCommand('Acme Corp'));
    }

    #[Test]
    public function propagatesRepositoryException(): void
    {
        $this->organizationRepository
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->handler->handle(new CreateOrganizationCommand('Acme Corp'));
    }
}
