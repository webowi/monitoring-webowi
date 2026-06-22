<?php

declare(strict_types=1);

namespace App\Logging\Ui\Ingest;

use App\Logging\Domain\LogSeverityEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints as Assert;

#[Exclude]
final readonly class IngestLogInput
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        #[Assert\NotNull]
        public \DateTimeImmutable $datetime,
        #[Assert\NotNull]
        public LogSeverityEnum $level,
        #[Assert\NotBlank]
        public string $message,
        public array $context = [],
    ) {}
}
