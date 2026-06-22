<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth\SignIn;

class AccountNotVerifiedException extends \Exception
{
    protected $message = 'Account not verified.';
}
