<?php

declare(strict_types=1);

namespace App\MonitoringWebowi\Handler;

use App\MonitoringWebowi\Handler\Transport\CurlTransport;
use App\MonitoringWebowi\Handler\Transport\TransportInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class IngestHandler extends AbstractProcessingHandler
{
    private readonly TransportInterface $transport;

    private readonly NormalizerFormatter $normalizer;

    private readonly ?\Closure $onFailure;

    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        ?TransportInterface $transport = null,
        ?callable $onFailure = null,
        int|string|Level $level = Level::Error,
        bool $bubble = false,
    ) {
        parent::__construct($level, $bubble);

        $this->transport = $transport ?? new CurlTransport();
        $this->normalizer = new NormalizerFormatter();
        $this->onFailure = null !== $onFailure ? \Closure::fromCallable($onFailure) : null;
    }

    protected function write(LogRecord $record): void
    {
        /** @var array<string, mixed> $context */
        $context = $this->normalizer->normalizeValue($record->context);

        if ([] !== $record->extra) {
            /** @var array<string, mixed> $extra */
            $extra = $this->normalizer->normalizeValue($record->extra);
            $context['_monologExtra'] = $extra;
        }

        $payload = json_encode([
            'datetime' => $record->datetime->format(\DATE_ATOM),
            'level'    => $record->level->toPsrLogLevel(),
            'message'  => $record->message,
            'context'  => $context,
        ], \JSON_THROW_ON_ERROR);

        try {
            $this->transport->send($this->url, $this->apiKey, $payload);
        } catch (\Throwable $e) {
            if (null !== $this->onFailure) {
                ($this->onFailure)($e);
            }
        }
    }
}
