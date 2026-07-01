<?php

declare(strict_types=1);

namespace App\Identity\Domain\Organization;

use App\Identity\Infrastructure\Db\OrganizationRepository;
use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table]
#[Gedmo\Loggable]
#[Vich\Uploadable]
class Organization implements TimestampableResourceInterface
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
        #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
        #[Assert\NotNull]
        #[Assert\Length(
            max: 191
        )]
        public string $name,
        #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
        #[Assert\Length(
            max: 191
        )]
        public ?string $slug = null,
        #[Vich\UploadableField(mapping: 'logos', fileNameProperty: 'logo', size: 'logoSize')]
        public ?File $logoFile = null,
        #[ORM\Column(nullable: true)]
        public ?string $logo = null,
        #[ORM\Column(nullable: true)]
        public ?int $logoSize = null,
    ) {}

    public static function register(
        string $name,
    ): self {
        return new self(
            uuid: Uuid::v4(),
            name: $name,
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @infection-ignore-all
     *
     * @codeCoverageIgnore
     */
    public function setLogoFile(?File $logoFile = null): void
    {
        $this->logoFile = $logoFile;

        if (null !== $logoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getLogoUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (str_contains($this->logo, '/')) {
            return $this->logo;
        }

        return \sprintf('/uploads/images/logos/%s', $this->logo);
    }
}
