<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\CreateProject;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\CreateProject\CreateProjectCommand;
use App\Projects\Application\CreateProject\CreateProjectHandler;
use App\Projects\Application\Exception\CannotSaveProjectException;
use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectRepositoryInterface;
use App\Projects\Domain\ProjectStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class CreateProjectHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private MockObject&LoggerInterface $logger;

    private CreateProjectHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateProjectHandler(
            $this->projectRepository,
            $this->currentUserFetcher,
            $this->logger
        );
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    #[Test]
    public function createsProjectForCurrentOrganization(): void
    {
        $organizationId = Uuid::v4();
        $command = new CreateProjectCommand('Test Project', ProjectPlatformEnum::VITE_REACT);

        $this->projectRepository->expects($this->once())->method('existsByName')->with('Test Project')->willReturn(false);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->once())->method('save');

        $result = $this->handler->handle($command);

        $this->assertSame('Test Project', $result->name);
        $this->assertSame(ProjectPlatformEnum::VITE_REACT, $result->platform);
        $this->assertSame(ProjectStatusEnum::ACTIVE, $result->status);
    }

    #[Test]
    public function throwsWhenNameAlreadyExists(): void
    {
        $command = new CreateProjectCommand('Test Project', ProjectPlatformEnum::SYMFONY);

        $this->projectRepository->expects($this->once())->method('existsByName')->with('Test Project')->willReturn(true);
        $this->projectRepository->expects($this->never())->method('save');

        $this->expectException(ProjectNameAlreadyExistsException::class);

        $this->handler->handle($command);
    }

    #[Test]
    public function throwsWhenSaveFailed(): void
    {
        $organizationId = Uuid::v4();
        $command = new CreateProjectCommand('Test Project', ProjectPlatformEnum::VITE_REACT);

        $this->projectRepository->expects($this->once())->method('existsByName')->with('Test Project')->willReturn(false);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->once())->method('save')
            ->willThrowException($exception = new \Exception());
        $this->logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                'Failed to create project: ' . $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

        $this->expectException(CannotSaveProjectException::class);

        $this->handler->handle($command);
    }
}
