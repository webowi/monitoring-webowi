<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Db;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\Organization\OrganizationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Organization>
 *
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null, $limit = null, $offset = null)
 *
 * @codeCoverageIgnore Simply repository
 *
 * @infection-ignore-all
 */
class OrganizationRepository extends ServiceEntityRepository implements OrganizationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function save(Organization $organization): void
    {
        $this->getEntityManager()->persist($organization);
        $this->getEntityManager()->flush();
    }

    public function getById(Uuid $organizationId): ?Organization
    {
        return $this->find($organizationId);
    }
}
