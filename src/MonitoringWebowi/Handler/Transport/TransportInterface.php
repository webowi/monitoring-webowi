<?php

declare(strict_types=1);

namespace App\MonitoringWebowi\Handler\Transport;

interface TransportInterface
{
    /**
     * @throws TransportException when the request could not be delivered or was rejected
     */
    public function send(string $url, string $apiKey, string $jsonPayload): void;
}
