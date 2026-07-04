<?php

declare(strict_types=1);

namespace App\Projects\Application\Exception;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class IngestionKeyAlreadyExistsException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Project already has an active ingestion key.', Response::HTTP_CONFLICT);
    }
}
