<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\CreateAccount;

use App\Identity\Application\CreateAccount\CreateAccountCommand;
use App\Identity\Application\CreateAccount\CreateAccountHandler;
use App\Identity\Application\CreateOrganization\CreateOrganizationHandler;
use App\Identity\Application\CreateOrganization\CreateOrganizationResult;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserExistException;
use App\Identity\Domain\User\UserFactory;
use App\Identity\Domain\User\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class CreateAccountHandlerTest extends TestCase
{
    private MockObject&UserFactory $userFactory;

    private MockObject&UserRepositoryInterface $userRepository;

    private MockObject&UserPasswordHasherInterface $passwordHasher;

    private MockObject&CreateOrganizationHandler $createOrganizationHandler;

    private MockObject&EntityManagerInterface $entityManager;

    private CreateAccountHandler $handler;

    protected function setUp(): void
    {
        $this->userFactory               = $this->createMock(UserFactory::class);
        $this->userRepository            = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher            = $this->createMock(UserPasswordHasherInterface::class);
        $this->createOrganizationHandler = $this->createMock(CreateOrganizationHandler::class);
        $this->entityManager             = $this->createMock(EntityManagerInterface::class);

        $this->handler = new CreateAccountHandler(
            $this->userFactory,
            $this->userRepository,
            $this->passwordHasher,
            $this->createOrganizationHandler,
            $this->entityManager,
        );
    }

    private function makeCommand(
        string $email = 'user@example.com',
        string $password = 'secret123',
        string $org = 'Acme Corp',
    ): CreateAccountCommand {
        return new CreateAccountCommand($email, $password, $org);
    }

    private function stubSuccessfulOrgCreation(Uuid $orgId): void
    {
        $this->createOrganizationHandler
            ->method('handle')
            ->willReturn(new CreateOrganizationResult($orgId));
    }

    private function stubSuccessfulUserCreation(Uuid $orgId): User
    {
        $user = User::register($orgId, new Email('user@example.com'));
        $this->userFactory->method('create')->willReturn($user);

        return $user;
    }

    #[Test]
    public function commitsTransactionOnSuccess(): void
    {
        $orgId = Uuid::v4();
        $this->stubSuccessfulOrgCreation($orgId);
        $this->stubSuccessfulUserCreation($orgId);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');
        $this->userRepository->expects($this->once())->method('save');

        $this->handler->handle($this->makeCommand());
    }

    #[Test]
    public function activatesUserBeforeSaving(): void
    {
        $orgId = Uuid::v4();
        $this->stubSuccessfulOrgCreation($orgId);
        $user = $this->stubSuccessfulUserCreation($orgId);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $u) => $u->isVerified()));

        $this->handler->handle($this->makeCommand());
    }

    #[Test]
    public function hashesPasswordBeforeSaving(): void
    {
        $orgId = Uuid::v4();
        $this->stubSuccessfulOrgCreation($orgId);
        $user = $this->stubSuccessfulUserCreation($orgId);
        $realHash = password_hash('secret123', \PASSWORD_BCRYPT);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn($realHash);

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $u) => $u->verifyPassword('secret123')));

        $this->handler->handle($this->makeCommand());
    }

    #[Test]
    public function rollsBackAndRethrowsOnUserExistException(): void
    {
        $orgId = Uuid::v4();
        $this->stubSuccessfulOrgCreation($orgId);
        $this->userFactory->method('create')->willThrowException(new UserExistException());

        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(UserExistException::class);

        $this->handler->handle($this->makeCommand());
    }

    #[Test]
    public function rollsBackAndRethrowsOnUnexpectedException(): void
    {
        $orgId = Uuid::v4();
        $this->stubSuccessfulOrgCreation($orgId);
        $this->userFactory->method('create')->willThrowException(new \RuntimeException('unexpected'));

        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unexpected');

        $this->handler->handle($this->makeCommand());
    }
}
