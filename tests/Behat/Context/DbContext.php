<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Tests\Behat\Exception\RowsFoundException;
use App\Tests\Behat\Exception\RowsNotFoundException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final class DbContext implements Context
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @Then on the table from domain :domainName I can find a row:
     */
    public function onDomainTableICanFindARow(string $domainName, TableNode $table): void
    {
        $hash = $table->getRowsHash();
        if (0 === (int) $this->executeQueryFromDomainTable($domainName, $hash)) {
            throw new RowsNotFoundException($domainName, $hash);
        }
        unset($hash);
    }

    /**
     * @Then on the table from domain :domainName I cannot find a row:
     */
    public function onDomainTableICannotFindARow(string $domainName, TableNode $table): void
    {
        $hash = $table->getRowsHash();
        if (0 !== (int) $this->executeQueryFromDomainTable($domainName, $hash)) {
            throw new RowsFoundException($domainName, $hash);
        }
        unset($hash);
    }

    /**
     * @param array<string, string> $hash
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function executeQueryFromDomainTable(string $domainName, array $hash): mixed
    {
        $query = $this->prepareQueryFromDomainTable($domainName);

        $query->select('COUNT(e)');
        foreach ($hash as $column => $value) {
            if (Uuid::isValid($value)) {
                $query->andWhere(\sprintf('e.%s = :uuid', $column))
                    ->setParameter('uuid', $value, 'uuid');
            } elseif ('NULL' === $value) {
                $query->andWhere(\sprintf('e.%s IS NULL', $column));
            } elseif ('NOT NULL' === $value) {
                $query->andWhere(\sprintf('e.%s IS NOT NULL', $column));
            } elseif (!\in_array($value, ['NULL', 'NOT NULL', 'TRUE', 'FALSE'], true)) {
                $query->andWhere(\sprintf('e.%s = :%s', $column, str_replace('.', '_', $column)));
                $query->setParameter(str_replace('.', '_', $column), $value);
            }
        }

        return $query->getQuery()->getSingleScalarResult();
    }

    private function prepareQueryFromDomainTable(string $domainName, string $alias = 'e'): QueryBuilder
    {
        $domainPath = str_starts_with($domainName, 'App') ? $domainName : \sprintf('App\%s\Domain\%s', $domainName, $domainName);
        $query = $this->entityManager->createQueryBuilder();
        $query->from($domainPath, $alias);

        return $query;
    }
}
