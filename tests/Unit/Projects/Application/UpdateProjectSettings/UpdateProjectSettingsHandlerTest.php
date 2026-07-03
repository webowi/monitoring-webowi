<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\UpdateProjectSettings;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNameAlreadyExistsException;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\UpdateProjectSettings\UpdateProjectSettingsCommand;
use App\Projects\Application\UpdateProjectSettings\UpdateProjectSettingsHandler;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectRepositoryInterface;
use App\Projects\Domain\ProjectStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UpdateProjectSettingsHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private UpdateProjectSettingsHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);

        $this->handler = new UpdateProjectSettingsHandler(
            $this->projectRepository,
            $this->currentUserFetcher,
        );
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    #[Test]
    public function updatesNamePlatformAndStatusInOneSave(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Old Name', $projectUuid, ProjectStatusEnum::ACTIVE, ProjectPlatformEnum::SYMFONY);
        $command = new UpdateProjectSettingsCommand($projectUuid, 'New Name', ProjectPlatformEnum::VITE_REACT, ProjectStatusEnum::INACTIVE);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->once())->method('existsByName')->with('New Name')->willReturn(false);
        $this->projectRepository->expects($this->once())->method('save')->with($project);

        $result = $this->handler->handle($command);

        $this->assertSame('New Name', $result->name);
        $this->assertSame(ProjectPlatformEnum::VITE_REACT, $result->platform);
        $this->assertSame(ProjectStatusEnum::INACTIVE, $result->status);
    }

    #[Test]
    public function doesNotCheckUniquenessWhenNameIsUnchanged(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Same Name', $projectUuid);
        $command = new UpdateProjectSettingsCommand($projectUuid, 'Same Name', ProjectPlatformEnum::VITE_REACT);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->never())->method('existsByName');
        $this->projectRepository->expects($this->once())->method('save')->with($project);

        $result = $this->handler->handle($command);

        $this->assertSame('Same Name', $result->name);
        $this->assertSame(ProjectPlatformEnum::VITE_REACT, $result->platform);
    }

    #[Test]
    public function updatesOnlyProvidedFields(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Old Name', $projectUuid, ProjectStatusEnum::ACTIVE, ProjectPlatformEnum::SYMFONY);
        $command = new UpdateProjectSettingsCommand($projectUuid, status: ProjectStatusEnum::INACTIVE);

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->never())->method('existsByName');
        $this->projectRepository->expects($this->once())->method('save')->with($project);

        $result = $this->handler->handle($command);

        $this->assertSame('Old Name', $result->name);
        $this->assertSame(ProjectPlatformEnum::SYMFONY, $result->platform);
        $this->assertSame(ProjectStatusEnum::INACTIVE, $result->status);
    }

    #[Test]
    public function throwsWhenProjectNotFound(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $command = new UpdateProjectSettingsCommand($projectUuid, name: 'New Name');

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn(null);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->never())->method('save');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($command);
    }

    #[Test]
    public function throwsWhenProjectBelongsToAnotherOrganization(): void
    {
        $projectUuid = Uuid::v4();
        $project = Project::register(Uuid::v4(), 'Old Name', $projectUuid);
        $command = new UpdateProjectSettingsCommand($projectUuid, name: 'New Name');

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser(Uuid::v4()));
        $this->projectRepository->expects($this->never())->method('save');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($command);
    }

    #[Test]
    public function throwsWhenNameAlreadyExists(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = Project::register($organizationId, 'Old Name', $projectUuid);
        $command = new UpdateProjectSettingsCommand($projectUuid, name: 'Taken Name');

        $this->projectRepository->expects($this->once())->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->expects($this->once())->method('fetchUser')->willReturn($this->buildUser($organizationId));
        $this->projectRepository->expects($this->once())->method('existsByName')->with('Taken Name')->willReturn(true);
        $this->projectRepository->expects($this->never())->method('save');

        $this->expectException(ProjectNameAlreadyExistsException::class);

        $this->handler->handle($command);
    }
}
