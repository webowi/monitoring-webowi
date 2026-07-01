<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\GetProject;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\GetProject\GetProjectHandler;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GetProjectHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private GetProjectHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);

        $this->handler = new GetProjectHandler(
            $this->projectRepository,
            $this->currentUserFetcher,
        );
    }

    private function buildProject(Uuid $uuid, Uuid $organizationId): Project
    {
        return Project::register($organizationId, 'Test Project');
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    #[Test]
    public function returnsProjectWhenOwned(): void
    {
        $organizationId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $organizationId);
        $user = $this->buildUser($organizationId);

        $this->projectRepository->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);

        $result = $this->handler->handle($projectUuid);

        $this->assertSame($project, $result);
    }

    #[Test]
    public function throwsWhenProjectDoesNotExist(): void
    {
        $projectUuid = Uuid::v4();

        $this->projectRepository->method('getById')->with($projectUuid)->willReturn(null);
        $this->currentUserFetcher->method('fetchUser')->willReturn($this->buildUser(Uuid::v4()));

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid);
    }

    #[Test]
    public function throwsWhenProjectBelongsToDifferentOrganization(): void
    {
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, Uuid::v4());
        $user = $this->buildUser(Uuid::v4());

        $this->projectRepository->method('getById')->with($projectUuid)->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid);
    }
}
