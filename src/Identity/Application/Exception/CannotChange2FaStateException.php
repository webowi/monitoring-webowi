<?php

declare(strict_types=1);

namespace App\Account\Application\Exception;

class CannotChange2FaStateException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'exception.cannotChange2FaState';
}
