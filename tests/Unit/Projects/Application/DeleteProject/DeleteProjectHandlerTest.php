<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\DeleteProject;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\DeleteProject\DeleteProjectHandler;
use App\Projects\Application\Exception\ProjectCannotRemoveException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class DeleteProjectHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&IngestionKeyRepositoryInterface $ingestionKeyRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private MockObject&LoggerInterface $logger;

    private DeleteProjectHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->ingestionKeyRepository = $this->createMock(IngestionKeyRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new DeleteProjectHandler(
            $this->projectRepository,
            $this->ingestionKeyRepository,
            $this->currentUserFetcher,
            $this->logger
        );
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    #[Test]
    public function deletesProjectAndCascadesIngestionKeys(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Test Project', $projectUuid);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->ingestionKeyRepository->expects($this->once())->method('removeAllByProjectId')->with($projectUuid);
        $this->projectRepository->expects($this->once())->method('remove')->with($project);

        $this->handler->handle($projectUuid);
    }

    #[Test]
    public function throwsWhenProjectNotFound(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn(null);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->never())->method('remove');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid);
    }

    #[Test]
    public function throwsWhenProjectBelongsToAnotherOrganization(): void
    {
        $projectUuid = Uuid::v4();
        $project = Project::register(Uuid::v4(), 'Test Project', $projectUuid);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser(Uuid::v4()));
        $this->projectRepository->expects($this->never())->method('remove');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid);
    }

    #[Test]
    public function throwsWhenRemoveFailed(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Test Project', $projectUuid);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->ingestionKeyRepository->expects($this->once())->method('removeAllByProjectId')->with($projectUuid);
        $this->projectRepository->expects($this->once())->method('remove')
            ->willThrowException($exception = new \Exception());
        $this->logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                'Failed to delete project: ' . $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

        $this->expectException(ProjectCannotRemoveException::class);

        $this->handler->handle($projectUuid);
    }
}
