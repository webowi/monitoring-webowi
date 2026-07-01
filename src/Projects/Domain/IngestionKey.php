<?php

declare(strict_types=1);

namespace App\Projects\Domain;

use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use App\Projects\Infrastructure\IngestionKeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: IngestionKeyRepository::class)]
#[ORM\Index(name: 'idx_ingestion_key_hash', columns: ['key_hash'])]
#[ORM\Index(name: 'idx_ingestion_key_status', columns: ['status'])]
class IngestionKey implements TimestampableResourceInterface
{
    use TimestampableTrait;

    private const string DEFAULT_NAME = 'Default';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore-next-line property.unusedType */
    private ?int $id = null;

    private function __construct(
        #[ORM\Column(type: 'uuid', unique: true)]
        public Uuid $uuid,
        #[ORM\Column(type: 'uuid')]
        public Uuid $projectId,
        #[ORM\Column(type: Types::STRING, length: 191, nullable: false)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 191)]
        public string $name,

        /**
         * Hash tokenu (NIE plaintext).
         * Najczęściej: hash_hmac('sha256', $token, $appSecret) albo sodium_crypto_generichash.
         */
        #[ORM\Column(name: 'key_hash', type: Types::STRING, length: 128, unique: true, nullable: false)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $keyHash,
        #[ORM\Column(
            type: Types::STRING,
            length: 32,
            enumType: IngestionKeyStatusEnum::class,
            options: ['default' => IngestionKeyStatusEnum::ACTIVE],
        )]
        public IngestionKeyStatusEnum $status = IngestionKeyStatusEnum::ACTIVE,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        public ?\DateTimeImmutable $revokedAt = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        public ?\DateTimeImmutable $lastUsedAt = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        public ?\DateTimeImmutable $expiresAt = null,
        #[ORM\Column(name: 'key_value', type: Types::STRING, length: 255, nullable: true)]
        public ?string $keyValue = null,
    ) {}

    public static function new(
        Uuid $projectId,
        ?string $name,
        string $keyHash,
        ?string $keyValue = null,
    ): self {
        return new self(
            uuid: Uuid::v4(),
            projectId: $projectId,
            name: $name ?? self::DEFAULT_NAME,
            keyHash: $keyHash,
            keyValue: $keyValue,
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        if (IngestionKeyStatusEnum::ACTIVE !== $this->status) {
            return false;
        }

        if (null !== $this->expiresAt && $this->expiresAt <= new \DateTimeImmutable('now')) {
            return false;
        }

        return true;
    }

    public function revoke(?\DateTimeImmutable $at = null): void
    {
        $this->status = IngestionKeyStatusEnum::REVOKED;
        $this->revokedAt = $at ?? new \DateTimeImmutable('now');
    }

    public function markUsedNow(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable('now');
    }
}
