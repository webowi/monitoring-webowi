<?php

declare(strict_types=1);

namespace App\Identity\Ui\Organization\Gus\Exception;

class CannotGetGusDataException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'Cannot find gus data';
}
