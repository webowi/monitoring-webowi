<?php

namespace App\Tests\Unit\Kernel\Security;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\User\User;
use App\Kernel\Security\CurrentUserFetcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserFetcherTest extends TestCase
{
    private MockObject&Security $security;

    private CurrentUserFetcher $currentUserFetcher;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);

        $this->currentUserFetcher = new CurrentUserFetcher($this->security);
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
    public function fetchCurrentUserSuccessfully(): void
    {
        $user = $this->createMock(User::class);
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $result = $this->currentUserFetcher->fetchUser();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function fetchCompanyOfCurrentUserSuccessfully(): void
    {
        $organization = $this->createMock(Organization::class);
        $user = $this->createMock(User::class);
        $user
            ->expects($this->once())
            ->method('getOrganization')
            ->willReturn($organization);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $result = $this->currentUserFetcher->fetchUserOrganization();

        $this->assertSame($organization, $result);
    }
}