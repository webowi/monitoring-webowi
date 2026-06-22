<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure;

use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\IngestionKeyRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IngestionKey>
 *
 * @method IngestionKey|null find($id, $lockMode = null, $lockVersion = null)
 * @method IngestionKey|null findOneBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null)
 * @method IngestionKey[]    findAll()
 * @method IngestionKey[]    findBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null, $limit = null, $offset = null)
 *
 * @codeCoverageIgnore Simply repository
 *
 * @infection-ignore-all
 */
class IngestionKeyRepository extends ServiceEntityRepository implements IngestionKeyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngestionKey::class);
    }

    public function findOneActiveByKeyHash(string $keyHash): ?IngestionKey
    {
        $ingestionKey = $this->findOneBy(['keyHash' => $keyHash]);

        if (null === $ingestionKey || !$ingestionKey->isActive()) {
            return null;
        }

        return $ingestionKey;
    }
}
