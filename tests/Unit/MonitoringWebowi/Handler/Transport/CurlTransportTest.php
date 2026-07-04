<?php

declare(strict_types=1);

namespace App\Tests\Unit\MonitoringWebowi\Handler\Transport;

use App\MonitoringWebowi\Handler\Transport\CurlTransport;
use App\MonitoringWebowi\Handler\Transport\TransportException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CurlTransportTest extends TestCase
{
    private static string $baseUrl;

    /** @var resource */
    private static $serverProcess;

    public static function setUpBeforeClass(): void
    {
        $script = __DIR__ . '/fixtures/server.php';

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $port = random_int(20000, 60000);
            $process = proc_open(
                ['php', '-S', "127.0.0.1:{$port}", $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );

            if (false === $process) {
                continue;
            }

            if (self::waitUntilListening('127.0.0.1', $port)) {
                self::$serverProcess = $process;
                self::$baseUrl = "http://127.0.0.1:{$port}";

                return;
            }

            proc_terminate($process);
            proc_close($process);
        }

        self::fail('Could not start the PHP built-in server fixture for CurlTransport tests.');
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
    }

    private static function waitUntilListening(string $host, int $port): bool
    {
        for ($i = 0; $i < 50; ++$i) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 0.05);

            if (false !== $connection) {
                fclose($connection);

                return true;
            }

            usleep(20_000);
        }

        return false;
    }

    private function uniqueApiKey(): string
    {
        return 'test-key-' . bin2hex(random_bytes(8));
    }

    private function capturedRequestFor(string $apiKey): array
    {
        $path = sys_get_temp_dir() . '/curl_transport_test_' . md5($apiKey) . '.json';
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        unlink($path);

        return $decoded;
    }

    #[Test]
    public function sendsPayloadAndSucceedsOnAcceptedResponse(): void
    {
        $transport = new CurlTransport();
        $apiKey = $this->uniqueApiKey();

        $transport->send(self::$baseUrl . '/accepted', $apiKey, '{"message":"hello"}');

        $captured = $this->capturedRequestFor($apiKey);
        $this->assertSame('{"message":"hello"}', $captured['body']);
        $this->assertSame($apiKey, $captured['headers']['X-Ingestion-Key']);
        $this->assertSame('application/json', $captured['headers']['Content-Type']);
    }

    #[Test]
    public function doesNotLeakResponseBodyToOutput(): void
    {
        $transport = new CurlTransport();

        ob_start();
        $transport->send(self::$baseUrl . '/accepted', $this->uniqueApiKey(), '{}');
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function succeedsAtLowerBoundStatus200(): void
    {
        $transport = new CurlTransport();

        $transport->send(self::$baseUrl . '/status-200', $this->uniqueApiKey(), '{}');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function throwsAtUpperBoundStatus300(): void
    {
        $transport = new CurlTransport();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unexpected HTTP status code: 300');

        $transport->send(self::$baseUrl . '/status-300', $this->uniqueApiKey(), '{}');
    }

    #[Test]
    public function throwsWithStatusMessageWhenResponseIsRejected(): void
    {
        $transport = new CurlTransport();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unexpected HTTP status code: 401');

        $transport->send(self::$baseUrl . '/rejected', $this->uniqueApiKey(), '{}');
    }

    #[Test]
    public function throwsWithStatusMessageWhenResponseIsServerError(): void
    {
        $transport = new CurlTransport();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unexpected HTTP status code: 500');

        $transport->send(self::$baseUrl . '/server-error', $this->uniqueApiKey(), '{}');
    }

    #[Test]
    public function throwsWithCurlErrorMessageWhenHostIsUnreachable(): void
    {
        $transport = new CurlTransport();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/^cURL error \(\d+\): /');

        $transport->send('http://127.0.0.1:1', $this->uniqueApiKey(), '{}');
    }
}
