<?php

declare(strict_types=1);

namespace App\Logging\Application\Ingest;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class IngestionRateLimitExceededException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Too many log entries ingested for this project. Please slow down.', Response::HTTP_TOO_MANY_REQUESTS);
    }
}
