<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth\SignIn;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;

class InvalidCredentialsException extends \Exception implements TranslatableExceptionInterface
{
    /**
     * @var string
     */
    protected $message = 'Invalid credentials.';

    /**
     * @var int
     */
    protected $code = 401;
}
