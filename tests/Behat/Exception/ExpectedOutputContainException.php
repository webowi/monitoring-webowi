<?php

declare(strict_types=1);

namespace App\Tests\Behat\Exception;

class ExpectedOutputContainException extends \Exception
{
    public function __construct(string $expected, string $actualOutput)
    {
        parent::__construct(\sprintf(
            'Expected output to contain "%s", but got "%s"',
            $expected,
            $actualOutput
        ));
    }
}
