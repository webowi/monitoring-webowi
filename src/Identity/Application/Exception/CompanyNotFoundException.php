<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

class CompanyNotFoundException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'exception.companyNotFound';
}
