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
    /** @phpstan-ignore-next-line  property.unusedType */
    private ?int $id = null;

    private function __construct(
        #[ORM\Column(type: 'uuid', unique: true)]
        public Uuid $uuid,
        #[ORM\Column(type: 'uuid')]
        public Uuid $organizationId,
        #[ORM\Column(type: Types::STRING, length: 500, unique: true, nullable: false)]
        public string $name,
        #[ORM\Column(
            type: 'string',
            length: 191,
            nullable: false,
            enumType: ProjectStatusEnum::class,
            options: ['default' => ProjectStatusEnum::ACTIVE],
        )]
        public ProjectStatusEnum $status = ProjectStatusEnum::ACTIVE,
        #[ORM\Column(
            type: 'string',
            length: 50,
            enumType: ProjectPlatformEnum::class,
            options: ['default' => ProjectPlatformEnum::SYMFONY],
        )]
        public ProjectPlatformEnum $platform = ProjectPlatformEnum::SYMFONY,
    ) {}

    public static function register(
        Uuid $organizationId,
        string $name,
        ?Uuid $uuid = null,
        ProjectStatusEnum $status = ProjectStatusEnum::ACTIVE,
        ProjectPlatformEnum $platform = ProjectPlatformEnum::SYMFONY,
    ): self {
        return new self(
            uuid: $uuid ?? Uuid::v4(),
            organizationId: $organizationId,
            name: $name,
            status: $status,
            platform: $platform
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function belongsToOrganization(Uuid $organizationId): bool
    {
        return $this->organizationId->equals($organizationId);
    }
}
