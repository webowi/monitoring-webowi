<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kernel\Security;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\Organization\OrganizationRepositoryInterface;
use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Infrastructure\Security\SymfonyUserAdapter;
use App\Kernel\Security\CurrentUserFetcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class CurrentUserFetcherTest extends TestCase
{
    private MockObject&Security $security;

    private MockObject&OrganizationRepositoryInterface $organizationRepository;

    private CurrentUserFetcher $currentUserFetcher;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->organizationRepository = $this->createMock(OrganizationRepositoryInterface::class);

        $this->currentUserFetcher = new CurrentUserFetcher($this->security, $this->organizationRepository);
    }

    #[Test]
    public function throwExceptionWhenCurrentUserIsNotInstanceOfUser(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Current user is not an instance of User.');

        $this->currentUserFetcher->fetchUser();
    }

    #[Test]
    public function fetchUserOrNullReturnsNullWhenCurrentUserIsNotInstanceOfUser(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $result = $this->currentUserFetcher->fetchUserOrNull();

        $this->assertNull($result);
    }

    #[Test]
    public function fetchUserOrNullReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createMock(SymfonyUserAdapter::class);
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $user
            ->expects($this->once())
            ->method('getDomainUser')
            ->willReturn($domainUser = $this->createStub(User::class));

        $result = $this->currentUserFetcher->fetchUserOrNull();

        $this->assertSame($domainUser, $result);
    }

    #[Test]
    public function fetchCurrentUserSuccessfully(): void
    {
        $user = $this->createMock(SymfonyUserAdapter::class);
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $user
            ->expects($this->once())
            ->method('getDomainUser')
            ->willReturn($domainUser = $this->createStub(User::class));

        $result = $this->currentUserFetcher->fetchUser();

        $this->assertSame($domainUser, $result);
    }

    #[Test]
    public function fetchCompanyOfCurrentUserSuccessfully(): void
    {
        $user = new SymfonyUserAdapter(User::register($organizationId = Uuid::v4(), new Email('email@com.pl')));

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $this->organizationRepository
            ->expects($this->once())
            ->method('getById')
            ->with($organizationId)
            ->willReturn($organization = Organization::register('name'));

        $result = $this->currentUserFetcher->fetchUserOrganization();

        $this->assertSame($organization, $result);
    }

    #[Test]
    public function throExceptionWhenCurrentUserNonAssociatedToOrganization(): void
    {
        $user = new SymfonyUserAdapter(User::register($organizationId = Uuid::v4(), new Email('email@com.pl')));

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $this->organizationRepository
            ->expects($this->once())
            ->method('getById')
            ->with($organizationId)
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Current user does not have an associated organization.');

        $this->currentUserFetcher->fetchUserOrganization();
    }
}
