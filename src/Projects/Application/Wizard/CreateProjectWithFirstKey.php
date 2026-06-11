<?php

namespace App\Projects\Application\Wizard;

use App\Identity\Infrastructure\Db\OrganizationRepository;
use App\Projects\Domain\IngestionKey;
use App\Projects\Domain\Project;
use App\Projects\Domain\ProjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CreateProjectWithFirstKey
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizationRepository,
        private readonly string $appSecret = '123',
    ) {}

    public function __invoke(CreateProjectWithFirstKeyCommand $cmd): CreateProjectWithFirstKeyResult
    {
        $organization = $this->organizationRepository->find($cmd->organizationid);
        if ($organization === null) {
            throw new \RuntimeException('Organization not found');
        }

        $project = new Project();
        $project->setUuid(Uuid::v7());
        $project->setOrganization($organization);
        $project->setName($cmd->name);
        $project->setPlatform($cmd->platform);
        $project->setStatus(ProjectStatusEnum::ACTIVE);

        // token
        $plaintext = 'mon_ing_' . bin2hex(random_bytes(24));
        $hash = hash_hmac('sha256', $plaintext, $this->appSecret);

        $key = new IngestionKey();
        $key->setUuid(Uuid::v7());
        $key->setProject($project);
        $key->setName($cmd->keyName);
        $key->setKeyHash($hash);

        $this->em->persist($project);
        $this->em->persist($key);
        $this->em->flush();

        return new CreateProjectWithFirstKeyResult(
            projectId: (int) $project->getId(),
            plaintextToken: $plaintext,
        );
    }
}