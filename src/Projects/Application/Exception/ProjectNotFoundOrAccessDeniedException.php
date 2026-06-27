<?php

declare(strict_types=1);

namespace App\Projects\Application\Exception;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class ProjectNotFoundOrAccessDeniedException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Project not found.', Response::HTTP_NOT_FOUND);
    }
}
