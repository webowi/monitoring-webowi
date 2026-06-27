<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\RotateIngestionKey;

use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Kernel\Security\CurrentUserFetcher;
use App\Projects\Application\Exception\ProjectNotFoundOrAccessDeniedException;
use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use App\Projects\Application\RotateIngestionKey\RotateIngestionKeyHandler;
use App\Projects\Application\RotateIngestionKey\RotateIngestionKeyResult;
use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\IngestionKeyStatusEnum;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use App\Projects\Infrastructure\Security\IngestionKeyGenerator;
use App\Projects\Infrastructure\Security\IngestionKeyHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class RotateIngestionKeyHandlerTest extends TestCase
{
    private MockObject&ProjectRepositoryInterface $projectRepository;

    private MockObject&IngestionKeyRepositoryInterface $ingestionKeyRepository;

    private MockObject&CurrentUserFetcher $currentUserFetcher;

    private MockObject&IngestionKeyGenerator $keyGenerator;

    private MockObject&InstallSnippetBuilder $snippetBuilder;

    private RotateIngestionKeyHandler $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->ingestionKeyRepository = $this->createMock(IngestionKeyRepositoryInterface::class);
        $this->currentUserFetcher = $this->createMock(CurrentUserFetcher::class);
        $this->keyGenerator = $this->createMock(IngestionKeyGenerator::class);
        $this->snippetBuilder = $this->createMock(InstallSnippetBuilder::class);

        $this->handler = new RotateIngestionKeyHandler(
            $this->projectRepository,
            $this->ingestionKeyRepository,
            $this->currentUserFetcher,
            $this->keyGenerator,
            new IngestionKeyHasher('test-secret'),
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

    private function buildActiveKey(Uuid $projectId, string $name = 'Test Key'): IngestionKey
    {
        return (new IngestionKey())
            ->setUuid(Uuid::v4())
            ->setProjectId($projectId)
            ->setName($name)
            ->setKeyHash('oldhash')
            ->setKeyValue('mon_ing_oldkey00000000000000000000000000');
    }

    #[Test]
    public function revokesOldKeyAndReturnsNewKeyWhenActiveKeyExists(): void
    {
        $orgId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $orgId);
        $user = $this->buildUser($orgId);
        $oldKey = $this->buildActiveKey($projectUuid, 'My Key');
        $generatedPlaintext = 'mon_ing_' . str_repeat('a', 32);

        $this->projectRepository->method('getById')->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);
        $this->ingestionKeyRepository->method('findActiveByProjectId')->willReturn($oldKey);
        $this->keyGenerator->method('generate')->willReturn($generatedPlaintext);
        $this->snippetBuilder->method('build')->with($generatedPlaintext)->willReturn('snippet-content');

        $result = $this->handler->handle($projectUuid);

        $this->assertInstanceOf(RotateIngestionKeyResult::class, $result);
        $this->assertSame($generatedPlaintext, $result->value);
        $this->assertSame('snippet-content', $result->snippet);
        $this->assertNotNull($result->keyUuid);
        $this->assertSame(IngestionKeyStatusEnum::REVOKED, $oldKey->getStatus());
    }

    #[Test]
    public function createsNewKeyWithDefaultNameWhenNoPriorKeyExists(): void
    {
        $orgId = Uuid::v4();
        $projectUuid = Uuid::v4();
        $project = $this->buildProject($projectUuid, $orgId);
        $user = $this->buildUser($orgId);
        $generatedPlaintext = 'mon_ing_' . str_repeat('b', 32);

        $this->projectRepository->method('getById')->willReturn($project);
        $this->currentUserFetcher->method('fetchUser')->willReturn($user);
        $this->ingestionKeyRepository->method('findActiveByProjectId')->willReturn(null);
        $this->keyGenerator->method('generate')->willReturn($generatedPlaintext);
        $this->snippetBuilder->method('build')->with($generatedPlaintext)->willReturn('snippet-no-prior');
        $this->ingestionKeyRepository->expects($this->once())->method('save');

        $result = $this->handler->handle($projectUuid);

        $this->assertSame($generatedPlaintext, $result->value);
        $this->assertSame('snippet-no-prior', $result->snippet);
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
