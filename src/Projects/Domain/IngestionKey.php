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

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; /** @phpstan-ignore property.unusedType */
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 191)]
    private string $name;

    /**
     * Hash tokenu (NIE plaintext).
     * Najczęściej: hash_hmac('sha256', $token, $appSecret) albo sodium_crypto_generichash.
     */
    #[ORM\Column(name: 'key_hash', type: Types::STRING, length: 128, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $keyHash;

    #[ORM\Column(
        type: Types::STRING,
        length: 32,
        enumType: IngestionKeyStatusEnum::class,
        options: ['default' => IngestionKeyStatusEnum::ACTIVE],
    )]
    private IngestionKeyStatusEnum $status = IngestionKeyStatusEnum::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'key_value', type: Types::STRING, length: 255, nullable: true)]
    private ?string $keyValue = null;

    public function getKeyValue(): ?string
    {
        return $this->keyValue;
    }

    public function setKeyValue(?string $keyValue): self
    {
        $this->keyValue = $keyValue;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
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

    public function getProjectId(): Uuid
    {
        return $this->projectId;
    }

    public function setProjectId(Uuid $projectId): self
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): self
    {
        $this->keyHash = $keyHash;

        return $this;
    }

    public function getStatus(): IngestionKeyStatusEnum
    {
        return $this->status;
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

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsedNow(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable('now');
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
