<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging\Application\List;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Logging\Application\List\ListProjectLogsHandler;
use App\Logging\Application\List\ProjectNotFoundOrAccessDeniedException;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ListProjectLogsHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&LogEntryRepositoryInterface $logEntryRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private ListProjectLogsHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->logEntryRepository = $this->createMock(LogEntryRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);

        $this->handler = new ListProjectLogsHandler(
            $this->projectRepository,
            $this->logEntryRepository,
            $this->currentUserFetcher,
        );
    }

    private function buildProject(Uuid $uuid, Uuid $organizationId): Project
    {
        return (new Project())
            ->setUuid($uuid)
            ->setOrganizationId($organizationId)
            ->setName('Test Project ' . $uuid);
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    #[Test]
    public function returnsLogEntriesWhenProjectBelongsToCurrentUsersOrganization(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $organizationId);
        $user = $this->buildUser($organizationId);

        $this->projectRepository
            ->expects($this->once())
            ->method('getById')
            ->with($projectUuid)
            ->willReturn($project);

        $this->currentUserFetcher
            ->expects($this->once())
            ->method('fetchUser')
            ->willReturn($user);

        $expectedLogEntries = [];
        $this->logEntryRepository
            ->expects($this->once())
            ->method('getByProjectId')
            ->with($projectUuid, 50, 0)
            ->willReturn($expectedLogEntries);

        $result = $this->handler->handle($projectUuid, 50, 0);

        $this->assertSame($expectedLogEntries, $result);
    }

    #[Test]
    public function throwsWhenProjectBelongsToADifferentOrganization(): void
    {
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, Uuid::v4());
        $user = $this->buildUser(Uuid::v4());

        $this->projectRepository
            ->method('getById')
            ->with($projectUuid)
            ->willReturn($project);

        $this->currentUserFetcher
            ->method('fetchUser')
            ->willReturn($user);

        $this->logEntryRepository->expects($this->never())->method('getByProjectId');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid, 50, 0);
    }

    #[Test]
    public function throwsWhenProjectDoesNotExist(): void
    {
        $projectUuid = Uuid::v4();

        $this->projectRepository
            ->method('getById')
            ->with($projectUuid)
            ->willReturn(null);

        $this->currentUserFetcher
            ->method('fetchUser')
            ->willReturn($this->buildUser(Uuid::v4()));

        $this->logEntryRepository->expects($this->never())->method('getByProjectId');

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid, 50, 0);
    }
}
