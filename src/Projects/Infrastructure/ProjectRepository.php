<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure;

use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @codeCoverageIgnore Simply repository
 *
 * @infection-ignore-all
 */
class ProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function countByOrganizationId(Uuid $organizationId): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.organization', 'c')
            ->andWhere('c.uuid = :organizationId')
            ->setParameter('organizationId', $organizationId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return iterable<Project>
     */
    public function getByOrganizationId(Uuid $organizationId): iterable
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.organization', 'c')
            ->andWhere('c.uuid = :organizationId')
            ->setParameter('organizationId', $organizationId, 'uuid')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->toIterable();
    }

    public function getById(Uuid $projectId): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('c.uuid = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function remove(Project $project): void
    {
        $this->getEntityManager()->remove($project);
        $this->getEntityManager()->flush();
    }
}
