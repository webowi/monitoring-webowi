<?php

declare(strict_types=1);

namespace App\Projects\Domain;

interface IngestionKeyRepositoryInterface
{
    public function findOneActiveByKeyHash(string $keyHash): ?IngestionKey;
}
