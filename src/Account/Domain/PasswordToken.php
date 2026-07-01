<?php

declare(strict_types=1);

namespace App\Account\Domain;

use App\Account\Infrastructure\PasswordTokenRepository;
use App\Kernel\EventSubscriber\UuidResourceInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Random\RandomException;
use Symfony\Component\Uid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: PasswordTokenRepository::class)]
class PasswordToken implements UuidResourceInterface
{
    private const TOKEN_LENGTH = 32;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $token;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiredAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $activatedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $updatedBy;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'passwordTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct(
        User $user,
        ?string $token = null,
        ?\DateTimeImmutable $expiredAt = null,
        ?\DateTimeImmutable $activatedAt = null,
        ?string $updatedBy = null
    ) {
        $this->user = $user;
        $this->token = $token;
        $this->expiredAt = $expiredAt;
        $this->activatedAt = $activatedAt;
        $this->updatedBy = $updatedBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(?Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return $this->expiredAtIsInTheFuture($now);
    }

    public function expiredAtIsInTheFuture(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiredAt && $this->expiredAt >= $now;
    }

    /**
     * @throws RandomException
     */
    public static function generateForMonth(User $user): self
    {
        return PasswordToken::generateForDate($user, '+1 month');
    }

    /**
     * @throws RandomException
     */
    public static function generateForOneDay(User $user): self
    {
        return PasswordToken::generateForDate($user, '+1 day');
    }

    /**
     * @throws RandomException
     * @throws \Exception
     */
    public static function generateForDate(User $user, string $date): self
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $expiredAt = new \DateTimeImmutable($date);

        return new self($user, token: $token, expiredAt: $expiredAt);
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function isTokenSame(string $token): bool
    {
        return $this->token === $token;
    }

    public function verify(string $activatedBy = 'system'): self
    {
        $this->activatedAt = new \DateTimeImmutable('now');
        $this->updatedBy = $activatedBy;
        $this->user->verify();

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function isActivated(): bool
    {
        return null !== $this->activatedAt;
    }
}
