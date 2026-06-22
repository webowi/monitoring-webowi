<?php

declare(strict_types=1);

namespace App\Projects\Domain;

use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use App\Projects\Infrastructure\ProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project implements TimestampableResourceInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; /** @phpstan-ignore property.unusedType */
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(type: 'uuid')]
    private Uuid $organizationId;

    #[ORM\Column(type: Types::STRING, length: 500, unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(
        type: 'string',
        length: 191,
        nullable: false,
        enumType: ProjectStatusEnum::class,
        options: ['default' => ProjectStatusEnum::ACTIVE],
    )]
    private ProjectStatusEnum $status = ProjectStatusEnum::ACTIVE;

    #[ORM\Column(
        type: 'string',
        length: 50,
        enumType: ProjectPlatformEnum::class,
        options: ['default' => ProjectPlatformEnum::SYMFONY],
    )]
    private ProjectPlatformEnum $platform = ProjectPlatformEnum::SYMFONY;

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(?Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organizationId;
    }

    public function setOrganizationId(Uuid $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): ProjectStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ProjectStatusEnum $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPlatform(): ProjectPlatformEnum
    {
        return $this->platform;
    }

    public function setPlatform(ProjectPlatformEnum $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function belongsToOrganization(Uuid $organizationId): bool
    {
        return $this->organizationId->equals($organizationId);
    }
}
