<?php

declare(strict_types=1);

namespace App\Identity\Domain;

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
    protected $message = 'exception.userExist';
}
