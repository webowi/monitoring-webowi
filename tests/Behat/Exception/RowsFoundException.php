<?php

declare(strict_types=1);

namespace App\Tests\Behat\Exception;

class RowsFoundException extends \Exception
{
    public function __construct(string $dbname, array $hash)
    {
        parent::__construct(sprintf('Found in %s rows like %s', $dbname, print_r($hash, true)));
    }
}
