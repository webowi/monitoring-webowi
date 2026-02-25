<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\CreateAccountCommand;
use App\Identity\Application\CreateAccountHandler;
use App\Identity\Application\Exception\CreateNewUserException;
use App\Identity\Domain\Company;
use App\Identity\Domain\CompanyFactory;
use App\Identity\Domain\User;
use App\Identity\Domain\UserFactory;
use App\Tests\Unit\ConsecutiveParamsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreateAccountHandlerTest extends TestCase
{
    use ConsecutiveParamsTrait;

    private MockObject&UserFactory $userFactory;

    private MockObject&CompanyFactory $companyFactory;

    private MockObject&LoggerInterface $logger;

    private MockObject&EntityManagerInterface $entityManager;

    private CreateAccountHandler $createAccountHandler;

    protected function setUp(): void
    {
        $this->companyFactory = $this->createMock(CompanyFactory::class);
        $this->userFactory = $this->createMock(UserFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->createAccountHandler = new CreateAccountHandler(
            $this->userFactory,
            $this->companyFactory,
            $this->logger,
            $this->entityManager
        );
    }

    public function testThrowExceptionAndLogErrorWhenCreatingAccountFails(): void
    {
        $email = 'test@email.com';
        $password = 'password';

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');
        $this->companyFactory
            ->expects($this->once())
            ->method('create')
            ->with($email)
            ->willThrowException($exception = new \Exception());
        $this->entityManager
            ->expects($this->never())
            ->method('commit');
        $this->entityManager
            ->expects($this->once())
            ->method('rollback');
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'An error occurred while creating a new account.',
                [
                    'exception' => $exception,
                    'email'     => $email,
                    'class'     => CreateAccountHandler::class,
                ]
            );

        $this->expectException(CreateNewUserException::class);

        $this->createAccountHandler->create(
            new CreateAccountCommand(
                $email,
                $password
            )
        );
    }

    public function testCommitChangesWhenUserCompanyAndSettingCreate(): void
    {
        $email = 'test@email.com';
        $password = 'password';

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');
        $this->companyFactory
            ->expects($this->once())
            ->method('create')
            ->with($email)
            ->willReturn($company = $this->createMock(Company::class));
        $this->userFactory
            ->expects($this->once())
            ->method('create')
            ->with($email, $password, $company)
            ->willReturn($user = $this->createMock(User::class));
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with(...$this->consecutiveParams([$company], [$user]));
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        $this->entityManager
            ->expects($this->once())
            ->method('commit');
        $this->entityManager
            ->expects($this->never())
            ->method('rollback');
        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->createAccountHandler->create(
            new CreateAccountCommand(
                $email,
                $password
            )
        );
    }
}
