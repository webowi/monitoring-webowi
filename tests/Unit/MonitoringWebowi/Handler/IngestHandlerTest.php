<?php

declare(strict_types=1);

namespace App\Tests\Unit\MonitoringWebowi\Handler;

use App\MonitoringWebowi\Handler\IngestHandler;
use App\MonitoringWebowi\Handler\Transport\TransportException;
use App\MonitoringWebowi\Handler\Transport\TransportInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IngestHandlerTest extends TestCase
{
    private MockObject&TransportInterface $transport;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
    }

    private function buildRecord(
        Level $level = Level::Error,
        string $message = 'something broke',
        array $context = [],
        array $extra = [],
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-07-04T10:00:00+00:00'),
            channel: 'app',
            level: $level,
            message: $message,
            context: $context,
            extra: $extra,
        );
    }

    #[Test]
    public function belowThresholdRecordsNeverReachTransport(): void
    {
        $this->transport->expects($this->never())->method('send');

        $handler = new IngestHandler('https://example.test/api/v1/logs/ingest', 'mon_ing_key', $this->transport);

        $handler->handle($this->buildRecord(Level::Warning));
    }

    #[Test]
    public function atThresholdRecordsAreSentWithCorrectPayloadShape(): void
    {
        $captured = null;
        $this->transport->expects($this->once())
            ->method('send')
            ->with(
                'https://example.test/api/v1/logs/ingest',
                'mon_ing_key',
                $this->callback(function (string $json) use (&$captured): bool {
                    $captured = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

                    return true;
                }),
            );

        $handler = new IngestHandler('https://example.test/api/v1/logs/ingest', 'mon_ing_key', $this->transport);
        $handler->handle($this->buildRecord(Level::Error, 'boom', ['key' => 'value']));

        $this->assertSame('2026-07-04T10:00:00+00:00', $captured['datetime']);
        $this->assertSame('error', $captured['level']);
        $this->assertSame('boom', $captured['message']);
        $this->assertSame(['key' => 'value'], $captured['context']);
    }

    #[Test]
    public function exceptionsInContextAreNormalizedToJsonSafeData(): void
    {
        $captured = null;
        $this->transport->method('send')->with(
            $this->anything(),
            $this->anything(),
            $this->callback(function (string $json) use (&$captured): bool {
                $captured = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

                return true;
            }),
        );

        $handler = new IngestHandler('https://example.test', 'key', $this->transport);
        $handler->handle($this->buildRecord(
            context: ['exception' => new \RuntimeException('nested failure')],
        ));

        $this->assertArrayHasKey('exception', $captured['context']);
        $this->assertSame('RuntimeException', $captured['context']['exception']['class']);
        $this->assertSame('nested failure', $captured['context']['exception']['message']);
    }

    #[Test]
    public function nonEmptyExtraIsMergedUnderReservedKey(): void
    {
        $captured = null;
        $this->transport->method('send')->with(
            $this->anything(),
            $this->anything(),
            $this->callback(function (string $json) use (&$captured): bool {
                $captured = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

                return true;
            }),
        );

        $handler = new IngestHandler('https://example.test', 'key', $this->transport);
        $handler->handle($this->buildRecord(extra: ['request_id' => 'abc-123']));

        $this->assertSame(['request_id' => 'abc-123'], $captured['context']['_monologExtra']);
    }

    #[Test]
    public function emptyExtraIsOmittedFromContext(): void
    {
        $captured = null;
        $this->transport->method('send')->with(
            $this->anything(),
            $this->anything(),
            $this->callback(function (string $json) use (&$captured): bool {
                $captured = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

                return true;
            }),
        );

        $handler = new IngestHandler('https://example.test', 'key', $this->transport);
        $handler->handle($this->buildRecord());

        $this->assertArrayNotHasKey('_monologExtra', $captured['context']);
    }

    #[Test]
    public function transportFailureIsSwallowedSilently(): void
    {
        $this->transport->method('send')->willThrowException(new TransportException('unreachable'));

        $handler = new IngestHandler('https://example.test', 'key', $this->transport);

        $handler->handle($this->buildRecord());

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function onFailureIsInvokedWhenTransportThrows(): void
    {
        $this->transport->method('send')->willThrowException(new TransportException('unreachable'));

        $received = null;
        $onFailure = function (\Throwable $e) use (&$received): void {
            $received = $e;
        };

        $handler = new IngestHandler('https://example.test', 'key', $this->transport, $onFailure);
        $handler->handle($this->buildRecord());

        $this->assertInstanceOf(TransportException::class, $received);
        $this->assertSame('unreachable', $received->getMessage());
    }

    #[Test]
    public function onFailureIsNotInvokedWhenTransportSucceeds(): void
    {
        $invoked = false;
        $onFailure = function () use (&$invoked): void {
            $invoked = true;
        };

        $handler = new IngestHandler('https://example.test', 'key', $this->transport, $onFailure);
        $handler->handle($this->buildRecord());

        $this->assertFalse($invoked);
    }

    #[Test]
    public function bubbleDefaultsToFalse(): void
    {
        $handler = new IngestHandler('https://example.test', 'key', $this->transport);

        $this->assertTrue($handler->handle($this->buildRecord()));
    }

    #[Test]
    public function bubbleFalseStopsPropagation(): void
    {
        $handler = new IngestHandler('https://example.test', 'key', $this->transport, bubble: false);

        $this->assertTrue($handler->handle($this->buildRecord()));
    }

    #[Test]
    public function bubbleTrueAllowsPropagation(): void
    {
        $handler = new IngestHandler('https://example.test', 'key', $this->transport, bubble: true);

        $this->assertFalse($handler->handle($this->buildRecord()));
    }
}
