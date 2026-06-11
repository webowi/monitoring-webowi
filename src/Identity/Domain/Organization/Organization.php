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
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\NotNull]
    #[Assert\Length(
        max: 191
    )]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\Length(
        max: 191
    )]
    private ?string $slug = null;

    #[Vich\UploadableField(mapping: 'logos', fileNameProperty: 'logo', size: 'logoSize')]
    private ?File $logoFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoSize = null;

    #[ORM\Column]
    private bool $isVerified = false;

    public function __construct(
    ) {
        $this->uuid = Uuid::v4();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): int
    {
        if (null === $this->id) {
            throw new \DomainException('Organization ID cannot be null');
        }

        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
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

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getLogoUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (str_contains($this->logo, '/')) {
            return $this->logo;
        }

        return sprintf('/uploads/images/logos/%s', $this->logo);
    }

    public function setLogoSize(?int $logoSize): void
    {
        $this->logoSize = $logoSize;
    }

    public function getLogoSize(): ?int
    {
        return $this->logoSize;
    }
}
