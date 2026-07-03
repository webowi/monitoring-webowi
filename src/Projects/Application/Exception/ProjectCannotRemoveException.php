<?php

declare(strict_types=1);

namespace App\Projects\Application\Exception;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProjectCannotRemoveException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Cannot remove project.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
