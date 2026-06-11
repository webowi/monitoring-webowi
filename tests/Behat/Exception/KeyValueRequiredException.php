<?php

declare(strict_types=1);

namespace App\Tests\Behat\Exception;

class KeyValueRequiredException extends \Exception
{
    public function __construct()
    {
        parent::__construct("You must provide a 'key' and 'value' column in your table node.");
    }
}
