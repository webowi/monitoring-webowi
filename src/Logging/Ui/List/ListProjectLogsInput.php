<?php

declare(strict_types=1);

namespace App\Logging\Ui\List;

use App\Logging\Domain\LogSeverityEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Exclude]
final readonly class ListProjectLogsInput
{
    public function __construct(
        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,
        #[Assert\GreaterThanOrEqual(0)]
        public int $offset = 0,
        public ?string $severity = null,
        public ?string $httpStatusCode = null,
    ) {}

    #[Assert\Callback]
    public function validateSeverity(ExecutionContextInterface $context): void
    {
        foreach ($this->severityTokens() as $token) {
            if (null === LogSeverityEnum::tryFrom($token)) {
                $context->buildViolation('validation.severity.invalid')
                    ->setParameter('{{ value }}', $token)
                    ->atPath('severity')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    public function validateHttpStatusCode(ExecutionContextInterface $context): void
    {
        if (null !== $this->httpStatusCode && 1 !== preg_match('/^(?:[1-5][0-9]{2}|[1-5]xx)$/', $this->httpStatusCode)) {
            $context->buildViolation('validation.httpStatusCode.invalid')
                ->setParameter('{{ value }}', $this->httpStatusCode)
                ->atPath('httpStatusCode')
                ->addViolation();
        }
    }

    /**
     * @return LogSeverityEnum[]
     */
    public function severities(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $token): ?LogSeverityEnum => LogSeverityEnum::tryFrom($token),
            $this->severityTokens(),
        )));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function httpStatusCodeRange(): ?array
    {
        if (null === $this->httpStatusCode) {
            return null;
        }

        if (1 === preg_match('/^([1-5])xx$/', $this->httpStatusCode, $matches)) {
            $base = (int) $matches[1] * 100;

            return [$base, $base + 99];
        }

        $code = (int) $this->httpStatusCode;

        return [$code, $code];
    }

    /**
     * @return string[]
     */
    private function severityTokens(): array
    {
        if (null === $this->severity) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $this->severity)),
            static fn (string $token): bool => '' !== $token,
        ));
    }
}
