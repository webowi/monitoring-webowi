<?php

declare(strict_types=1);

namespace App\Logging\Infrastructure;

use App\Logging\Domain\LogEntry;
use App\Logging\Domain\LogEntryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<LogEntry>
 *
 * @method LogEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogEntry[]    findAll()
 * @method LogEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @return iterable<LogEntry>
     */
    public function getByProjectId(Uuid $projectId, int $limit, int $offset): iterable
    {
        /** @var iterable<LogEntry> $result */
        $result = $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('l.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->toIterable();

        return $result;
    }
}
