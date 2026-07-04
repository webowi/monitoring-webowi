<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$headers = getallheaders();
$apiKey = $headers['X-Ingestion-Key'] ?? '';

if ('' !== $apiKey) {
    file_put_contents(
        sys_get_temp_dir() . '/curl_transport_test_' . md5((string) $apiKey) . '.json',
        json_encode([
            'path'    => $path,
            'headers' => $headers,
            'body'    => file_get_contents('php://input'),
        ], JSON_THROW_ON_ERROR),
    );
}

http_response_code(match ($path) {
    '/accepted'     => 202,
    '/status-200'   => 200,
    '/status-300'   => 300,
    '/rejected'     => 401,
    '/server-error' => 500,
    default         => 404,
});

echo 'response-body';
