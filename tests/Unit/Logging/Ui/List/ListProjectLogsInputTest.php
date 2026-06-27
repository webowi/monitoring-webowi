<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging\Ui\List;

use App\Logging\Domain\LogSeverityEnum;
use App\Logging\Ui\List\ListProjectLogsInput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ListProjectLogsInputTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    #[Test]
    public function validSingleSeverityProducesNoViolations(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(severity: 'error'));

        $this->assertCount(0, $violations);
    }

    #[Test]
    public function validMultiSeverityProducesNoViolations(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(severity: 'error,critical'));

        $this->assertCount(0, $violations);
    }

    #[Test]
    public function invalidSeverityTokenProducesAViolation(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(severity: 'bogus'));

        $this->assertViolationOnPath($violations, 'severity');
    }

    #[Test]
    public function oneInvalidTokenAmongValidOnesStillProducesAViolation(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(severity: 'error,bogus'));

        $this->assertViolationOnPath($violations, 'severity');
    }

    #[Test]
    public function validExactHttpStatusCodeProducesNoViolations(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(httpStatusCode: '500'));

        $this->assertCount(0, $violations);
    }

    #[Test]
    public function validClassShorthandHttpStatusCodeProducesNoViolations(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(httpStatusCode: '5xx'));

        $this->assertCount(0, $violations);
    }

    #[Test]
    public function malformedHttpStatusCodeProducesAViolation(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(httpStatusCode: '999'));

        $this->assertViolationOnPath($violations, 'httpStatusCode');
    }

    #[Test]
    public function nonNumericHttpStatusCodeProducesAViolation(): void
    {
        $violations = $this->validator->validate(new ListProjectLogsInput(httpStatusCode: '9xxx'));

        $this->assertViolationOnPath($violations, 'httpStatusCode');
    }

    #[Test]
    public function severitiesReturnsEmptyArrayWhenFilterAbsent(): void
    {
        $this->assertSame([], (new ListProjectLogsInput())->severities());
    }

    #[Test]
    public function severitiesReturnsParsedEnumCasesForMultipleTokens(): void
    {
        $this->assertSame(
            [LogSeverityEnum::ERROR, LogSeverityEnum::CRITICAL],
            (new ListProjectLogsInput(severity: 'error,critical'))->severities(),
        );
    }

    #[Test]
    public function severitiesTrimsWhitespaceAroundTokens(): void
    {
        $this->assertSame(
            [LogSeverityEnum::ERROR, LogSeverityEnum::CRITICAL],
            (new ListProjectLogsInput(severity: ' error , critical '))->severities(),
        );
    }

    #[Test]
    public function httpStatusCodeRangeReturnsNullWhenFilterAbsent(): void
    {
        $this->assertNull((new ListProjectLogsInput())->httpStatusCodeRange());
    }

    #[Test]
    public function httpStatusCodeRangeReturnsExactMinMaxForAnExactCode(): void
    {
        $this->assertSame([500, 500], (new ListProjectLogsInput(httpStatusCode: '500'))->httpStatusCodeRange());
    }

    #[Test]
    public function httpStatusCodeRangeReturnsClassRangeForShorthand(): void
    {
        $this->assertSame([500, 599], (new ListProjectLogsInput(httpStatusCode: '5xx'))->httpStatusCodeRange());
    }

    private function assertViolationOnPath(ConstraintViolationListInterface $violations, string $path): void
    {
        $this->assertGreaterThan(0, \count($violations));

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        $this->assertContains($path, $paths);
    }
}
