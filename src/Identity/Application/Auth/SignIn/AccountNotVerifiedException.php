<?php

namespace App\Identity\Application\Auth\SignIn;

class AccountNotVerifiedException extends \Exception
{
    protected $message = 'Account not verified.';
}