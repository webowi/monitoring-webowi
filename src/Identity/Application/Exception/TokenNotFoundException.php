<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

class TokenNotFoundException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'exception.tokenNotFound';
}
