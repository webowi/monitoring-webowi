<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use Symfony\Component\HttpFoundation\HeaderBag;

class HeaderState
{
    private HeaderBag $headers;

    public function __construct()
    {
        $this->headers = new HeaderBag();
    }

    public function getBag(): HeaderBag
    {
        return $this->headers;
    }

    public function clear(): void
    {
        $this->headers->replace([]);
    }
}
