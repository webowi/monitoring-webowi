<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Infrastructure\Security;

use App\Projects\Infrastructure\Security\IngestionKeyGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IngestionKeyGeneratorTest extends TestCase
{
    private IngestionKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new IngestionKeyGenerator();
    }

    #[Test]
    public function generatedKeyStartsWithMonIngPrefix(): void
    {
        $key = $this->generator->generate();

        $this->assertStringStartsWith('mon_ing_', $key);
    }

    #[Test]
    public function generatedKeyIsFortyCharacters(): void
    {
        $key = $this->generator->generate();

        $this->assertSame(40, \strlen($key));
    }

    #[Test]
    public function twoGeneratedKeysAreDifferent(): void
    {
        $key1 = $this->generator->generate();
        $key2 = $this->generator->generate();

        $this->assertNotSame($key1, $key2);
    }
}
