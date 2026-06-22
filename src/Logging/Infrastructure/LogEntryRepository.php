<?php

declare(strict_types=1);

namespace App\Logging\Infrastructure;

use App\Logging\Domain\LogEntry;
use App\Logging\Domain\LogEntryRepositoryInterface;
use App\Logging\Domain\LogSeverityEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<LogEntry>
 *
 * @method LogEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogEntry|null findOneBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null)
 * @method LogEntry[]    findAll()
 * @method LogEntry[]    findBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null, $limit = null, $offset = null)
 *
 * @codeCoverageIgnore Simply repository
 *
 * @infection-ignore-all
 */
class LogEntryRepository extends ServiceEntityRepository implements LogEntryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogEntry::class);
    }

    public function add(LogEntry $logEntry): void
    {
        $this->getEntityManager()->persist($logEntry);
        $this->getEntityManager()->flush();
    }

    /**
     * @param LogSeverityEnum[] $severities
     *
     * @return iterable<LogEntry>
     */
    public function getByProjectId(
        Uuid $projectId,
        int $limit,
        int $offset,
        array $severities = [],
        ?int $httpStatusCodeMin = null,
        ?int $httpStatusCodeMax = null,
    ): iterable {
        $queryBuilder = $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid');

        if ([] !== $severities) {
            $queryBuilder
                ->andWhere('l.severity IN (:severities)')
                ->setParameter('severities', array_map(static fn (LogSeverityEnum $severity): string => $severity->value, $severities));
        }

        if (null !== $httpStatusCodeMin && null !== $httpStatusCodeMax) {
            $queryBuilder
                ->andWhere('l.httpStatusCode BETWEEN :httpStatusCodeMin AND :httpStatusCodeMax')
                ->setParameter('httpStatusCodeMin', $httpStatusCodeMin)
                ->setParameter('httpStatusCodeMax', $httpStatusCodeMax);
        }

        /** @var iterable<LogEntry> $result */
        $result = $queryBuilder
            ->orderBy('l.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->toIterable();

        return $result;
    }
}
