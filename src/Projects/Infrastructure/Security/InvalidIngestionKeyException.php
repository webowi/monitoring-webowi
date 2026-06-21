<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure\Security;

use App\Kernel\TranslatableException\TranslatableExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class InvalidIngestionKeyException extends \Exception implements TranslatableExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Invalid or missing ingestion key.', Response::HTTP_UNAUTHORIZED);
    }
}
