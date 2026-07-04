<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\GetIngestionKey;

use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InstallSnippetBuilderTest extends TestCase
{
    private function projectDir(): string
    {
        return \dirname(__DIR__, 5);
    }

    #[Test]
    public function snippetContainsKeyValue(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('mon_ing_test_key');

        $this->assertStringContainsString('mon_ing_test_key', $snippet);
    }

    #[Test]
    public function snippetContainsIngestionUrl(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost:8000', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('http://localhost:8000/api/v1/logs/ingest', $snippet);
    }

    #[Test]
    public function stripsTrailingSlashFromAppUrl(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost:8000/', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('http://localhost:8000/api/v1/logs/ingest', $snippet);
        $this->assertStringNotContainsString('//api', $snippet);
    }

    #[Test]
    public function snippetEmbedsRealHandlerSource(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('final class IngestHandler', $snippet);
        $this->assertStringContainsString('final class CurlTransport', $snippet);
        $this->assertStringContainsString('interface TransportInterface', $snippet);
        $this->assertStringContainsString('final class TransportException', $snippet);
    }

    #[Test]
    public function snippetNamesDestinationPathsForEachEmbeddedFile(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('src/MonitoringWebowi/Handler/IngestHandler.php', $snippet);
        $this->assertStringContainsString('src/MonitoringWebowi/Handler/Transport/CurlTransport.php', $snippet);
        $this->assertStringContainsString('src/MonitoringWebowi/Handler/Transport/TransportInterface.php', $snippet);
        $this->assertStringContainsString('src/MonitoringWebowi/Handler/Transport/TransportException.php', $snippet);
    }

    #[Test]
    public function firstSourceBlockImmediatelyFollowsWiringSeparatedByOneBlankLine(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('mon_ing_test_key');

        $this->assertStringContainsString(
            "\$apiKey: 'mon_ing_test_key'\n\n// Create src/MonitoringWebowi/Handler/Transport/TransportInterface.php:",
            $snippet,
        );
    }

    #[Test]
    public function eachSourceBlockIsAFencedPhpBlockImmediatelyFollowingItsHeaderComment(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString(
            "// Create src/MonitoringWebowi/Handler/Transport/TransportException.php:\n```php\n<?php",
            $snippet,
        );
        $this->assertSame(8, substr_count($snippet, '```'));
    }

    #[Test]
    public function eachSourceBlockHasNoBlankLineBeforeItsClosingFence(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost', $this->projectDir());
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString("}\n```", $snippet);
        $this->assertStringNotContainsString("}\n\n```", $snippet);
    }
}
