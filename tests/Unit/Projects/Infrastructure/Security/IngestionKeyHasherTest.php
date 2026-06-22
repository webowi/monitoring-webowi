<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects\Infrastructure\Security;

use App\Projects\Infrastructure\Security\IngestionKeyHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IngestionKeyHasherTest extends TestCase
{
    #[Test]
    public function hashIsDeterministicForSameSecretAndPlaintext(): void
    {
        $hasher = new IngestionKeyHasher('app-secret');

        $this->assertSame($hasher->hash('plaintext-key'), $hasher->hash('plaintext-key'));
    }

    #[Test]
    public function differentPlaintextsProduceDifferentHashes(): void
    {
        $hasher = new IngestionKeyHasher('app-secret');

        $this->assertNotSame($hasher->hash('key-one'), $hasher->hash('key-two'));
    }

    #[Test]
    public function differentSecretsProduceDifferentHashesForSamePlaintext(): void
    {
        $first = new IngestionKeyHasher('secret-one');
        $second = new IngestionKeyHasher('secret-two');

        $this->assertNotSame($first->hash('same-key'), $second->hash('same-key'));
    }

    #[Test]
    public function hashMatchesExpectedHmacSha256Formula(): void
    {
        $hasher = new IngestionKeyHasher('app-secret');

        $this->assertSame(hash_hmac('sha256', 'plaintext-key', 'app-secret'), $hasher->hash('plaintext-key'));
    }
}
