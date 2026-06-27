<?php

declare(strict_types=1);

namespace App\Projects\Application\GetIngestionKey;

class InstallSnippetBuilder
{
    public function __construct(
        private readonly string $appUrl,
    ) {}

    public function build(string $keyValue): string
    {
        $ingestionUrl = rtrim($this->appUrl, '/') . '/api/v1/logs/ingest';

        return <<<YAML
        # Add to config/packages/monolog.yaml, inside the handlers section:
        monitoring_webowi:
            type: fingers_crossed
            action_level: error
            handler: monitoring_webowi_http
            bubble: false
        monitoring_webowi_http:
            type: service
            id: monitoring_webowi.ingest_handler
            level: debug

        # Register the handler in config/services.yaml:
        # monitoring_webowi.ingest_handler:
        #     class: App\\MonitoringWebowi\\Handler\\IngestHandler
        #     arguments:
        #         \$url: '{$ingestionUrl}'
        #         \$apiKey: '{$keyValue}'
        YAML;
    }
}
