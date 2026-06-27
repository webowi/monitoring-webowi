<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Application\GetIngestionKey;

use App\Projects\Application\GetIngestionKey\InstallSnippetBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InstallSnippetBuilderTest extends TestCase
{
    #[Test]
    public function snippetContainsKeyValue(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost');
        $snippet = $builder->build('mon_ing_test_key');

        $this->assertStringContainsString('mon_ing_test_key', $snippet);
    }

    #[Test]
    public function snippetContainsIngestionUrl(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost:8000');
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('http://localhost:8000/api/v1/logs/ingest', $snippet);
    }

    #[Test]
    public function stripsTrailingSlashFromAppUrl(): void
    {
        $builder = new InstallSnippetBuilder('http://localhost:8000/');
        $snippet = $builder->build('some-key');

        $this->assertStringContainsString('http://localhost:8000/api/v1/logs/ingest', $snippet);
        $this->assertStringNotContainsString('//api', $snippet);
    }
}
