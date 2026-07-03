<?php

declare(strict_types=1);

namespace App\Projects\Application\Exception;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProjectNameAlreadyExistsException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Project with this name already exists.', Response::HTTP_CONFLICT);
    }
}
