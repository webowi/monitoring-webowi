<?php

declare(strict_types=1);

namespace App\MonitoringWebowi\Handler\Transport;

final class CurlTransport implements TransportInterface
{
    private const int CONNECT_TIMEOUT_MS = 200;

    private const int TIMEOUT_MS = 300;

    public function send(string $url, string $apiKey, string $jsonPayload): void
    {
        /** @var \CurlHandle $handle */
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            \CURLOPT_POSTFIELDS     => $jsonPayload,
            \CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Ingestion-Key: ' . $apiKey,
            ],
            \CURLOPT_RETURNTRANSFER    => true,
            \CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            \CURLOPT_TIMEOUT_MS        => self::TIMEOUT_MS,
        ]);

        curl_exec($handle);

        $errno = curl_errno($handle);
        $error = curl_error($handle);
        /** @var int $statusCode */
        $statusCode = curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);

        curl_close($handle);

        if (0 !== $errno) {
            throw new TransportException(\sprintf('cURL error (%d): %s', $errno, $error));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException(\sprintf('Unexpected HTTP status code: %d', $statusCode));
        }
    }
}
