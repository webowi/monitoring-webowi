<?php

declare(strict_types=1);

namespace App\Tests\Behat\Exception;

class JsonNodeNotEqualException extends \Exception
{
    public function __construct(string $actual)
    {
        parent::__construct(sprintf("The node value is '%s'", \json_encode($actual, JSON_THROW_ON_ERROR)));
    }
}
