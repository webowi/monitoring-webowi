<?php

declare(strict_types=1);

namespace App\Logging\Domain;

use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use App\Logging\Infrastructure\LogEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: 'log_entry')]
#[ORM\Index(name: 'idx_log_entry_project_occurred_at', columns: ['project_id', 'occurred_at'])]
class LogEntry implements TimestampableResourceInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(
        #[ORM\Column(type: 'uuid', unique: true)]
        public readonly Uuid $uuid,

        #[ORM\Column(name: 'project_id', type: 'uuid')]
        public readonly Uuid $projectId,

        #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
        public readonly \DateTimeImmutable $occurredAt,

        #[ORM\Column(name: 'received_at', type: Types::DATETIME_IMMUTABLE)]
        public readonly \DateTimeImmutable $receivedAt,

        #[ORM\Column(type: Types::STRING, length: 32, enumType: LogSeverityEnum::class)]
        public readonly LogSeverityEnum $severity,

        #[ORM\Column(type: Types::TEXT)]
        public readonly string $message,

        #[ORM\Column(name: 'http_status_code', type: Types::SMALLINT, nullable: true)]
        public readonly ?int $httpStatusCode,

        #[ORM\Column(name: 'exception_class', type: Types::STRING, length: 255, nullable: true)]
        public readonly ?string $exceptionClass,

        #[ORM\Column(type: Types::JSON)]
        public readonly array $context,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function create(
        Uuid $projectId,
        \DateTimeImmutable $occurredAt,
        LogSeverityEnum $severity,
        string $message,
        ?int $httpStatusCode,
        ?string $exceptionClass,
        array $context,
    ): self {
        return new self(
            uuid: Uuid::v7(),
            projectId: $projectId,
            occurredAt: $occurredAt,
            receivedAt: new \DateTimeImmutable('now'),
            severity: $severity,
            message: $message,
            httpStatusCode: $httpStatusCode,
            exceptionClass: $exceptionClass,
            context: $context,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
