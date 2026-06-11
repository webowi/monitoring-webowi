<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use Symfony\Component\HttpFoundation\Response;

class ResponseState
{
    private ?Response $response = null;

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
