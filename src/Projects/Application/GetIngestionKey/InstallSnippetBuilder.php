<?php

declare(strict_types=1);

namespace App\Projects\Application\GetIngestionKey;

class InstallSnippetBuilder
{
    private const array HANDLER_FILES = [
        'Transport/TransportInterface.php',
        'Transport/TransportException.php',
        'Transport/CurlTransport.php',
        'IngestHandler.php',
    ];

    public function __construct(
        private readonly string $appUrl,
        private readonly string $projectDir,
    ) {}

    public function build(string $keyValue): string
    {
        $ingestionUrl = rtrim($this->appUrl, '/') . '/api/v1/logs/ingest';

        $wiring = <<<YAML
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

        $sourceBlocks = array_map(
            fn (string $relativePath): string => $this->renderSourceBlock($relativePath),
            self::HANDLER_FILES,
        );

        return $wiring . "\n\n" . implode("\n\n", $sourceBlocks);
    }

    private function renderSourceBlock(string $relativePath): string
    {
        $destination = 'src/MonitoringWebowi/Handler/' . $relativePath;

        /** @var string $contents */
        $contents = file_get_contents($this->projectDir . '/' . $destination);

        return "// Create {$destination}:\n```php\n" . trim($contents) . "\n```";
    }
}
