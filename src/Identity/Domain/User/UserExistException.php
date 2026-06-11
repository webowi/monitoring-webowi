<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
class UserExistException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'User with this email already exists.';
}
