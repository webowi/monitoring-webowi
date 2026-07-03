<?php

declare(strict_types=1);

namespace App\Projects\Domain;

use Symfony\Component\Uid\Uuid;

interface IngestionKeyRepositoryInterface
{
    public function findOneActiveByKeyHash(string $keyHash): ?IngestionKey;

    public function findActiveByProjectId(Uuid $projectId): ?IngestionKey;

    public function save(IngestionKey $key): void;

    public function removeAllByProjectId(Uuid $projectId): void;
}
