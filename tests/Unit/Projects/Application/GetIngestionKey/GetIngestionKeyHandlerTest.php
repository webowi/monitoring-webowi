<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\GetIngestionKey;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\GetIngestionKey\GetIngestionKeyHandler;
use App\Projects\Application\GetIngestionKey\GetIngestionKeyResult;
use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\IngestionKeyStatusEnum;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GetIngestionKeyHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&IngestionKeyRepositoryInterface $ingestionKeyRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private MockObject&InstallSnippetBuilder $snippetBuilder;

    private GetIngestionKeyHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->ingestionKeyRepository = $this->createMock(IngestionKeyRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);
        $this->snippetBuilder = $this->createMock(InstallSnippetBuilder::class);

        $this->handler = new GetIngestionKeyHandler(
            $this->projectRepository,
            $this->ingestionKeyRepository,
            $this->currentUserFetcher,
            $this->snippetBuilder,
        );
    }

    private function buildProject(Uuid $uuid, Uuid $organizationId): Project
    {
        return (new Project())
            ->setUuid($uuid)
            ->setOrganizationId($organizationId)
            ->setName('Test Project');
    }

    private function buildUser(Uuid $organizationId): User
    {
        return User::register($organizationId, new Email('owner@example.com'));
    }

    private function buildActiveKey(Uuid $projectId, string $keyValue): IngestionKey
    {
        return (new IngestionKey())
            ->setUuid(Uuid::v4())
            ->setProjectId($projectId)
            ->setName('Test Key')
            ->setKeyHash('hash')
            ->setKeyValue($keyValue);
    }

    #[Test]
    public function returnsResultWithKeyValueWhenActiveKeyExists(): void
    {
        $orgId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $orgId);
        $user = $this->buildUser($orgId);
        $key = $this->buildActiveKey($projectUuid, 'mon_ing_test');

        $this->projectRepository->method('getById')->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);
        $this->ingestionKeyRepository->method('findActiveByProjectId')->willReturn($key);
        $this->snippetBuilder->method('build')->with('mon_ing_test')->willReturn('snippet-content');

        $result = $this->handler->handle($projectUuid);

        $this->assertInstanceOf(GetIngestionKeyResult::class, $result);
        $this->assertSame('mon_ing_test', $result->value);
        $this->assertSame(IngestionKeyStatusEnum::ACTIVE->value, $result->status);
        $this->assertSame('snippet-content', $result->snippet);
        $this->assertNotNull($result->keyUuid);
    }

    #[Test]
    public function returnsNullValueWhenNoActiveKeyExists(): void
    {
        $orgId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $orgId);
        $user = $this->buildUser($orgId);

        $this->projectRepository->method('getById')->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);
        $this->ingestionKeyRepository->method('findActiveByProjectId')->willReturn(null);
        $this->snippetBuilder->method('build')->with('')->willReturn('snippet-no-key');

        $result = $this->handler->handle($projectUuid);

        $this->assertNull($result->value);
        $this->assertNull($result->keyUuid);
        $this->assertSame('none', $result->status);
    }

    #[Test]
    public function throwsWhenProjectDoesNotExist(): void
    {
        $this->projectRepository->method('getById')->willReturn(null);
        $this->currentUserFetcher->method('fetchUser')->willReturn($this->buildUser(Uuid::v4()));

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle(Uuid::v4());
    }

    #[Test]
    public function throwsWhenProjectBelongsToDifferentOrganization(): void
    {
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, Uuid::v4());
        $user = $this->buildUser(Uuid::v4());

        $this->projectRepository->method('getById')->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);

        $this->expectException(ProjectNotFoundOrAccessDeniedException::class);

        $this->handler->handle($projectUuid);
    }
}
