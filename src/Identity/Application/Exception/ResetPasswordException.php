<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

class ResetPasswordException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'exception.resetPassword';
}
