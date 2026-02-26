<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Infrastructure\CompanyRepository;
use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use App\Projects\Domain\Project;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
#[Gedmo\Loggable]
class Company implements TimestampableResourceInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company')]
    private Collection $users;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'company')]
    private Collection $projects;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\NotNull]
    #[Assert\Length(
        max: 191
    )]
    private ?string $name = null;

    #[ORM\Column(name: 'tin', type: Types::STRING, length: 168, nullable: true)]
    #[Assert\Length(
        min: 10,
        max: 10,
    )]
    private ?string $taxIdentificationNumber = null;

    #[ORM\Column(type: Types::STRING, length: 168, nullable: true)]
    #[Assert\Length(
        max: 20
    )]
    private ?string $regon = null;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\Length(
        max: 191
    )]
    private ?string $province = null;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\Length(
        max: 191
    )]
    private ?string $street = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Length(
        max: 10
    )]
    private ?string $zipCode = null;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\Length(
        max: 191
    )]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 15, nullable: true)]
    #[Assert\Length(max: 15)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
    #[Assert\Length(
        max: 180
    )]
    #[Assert\Email]
    private ?string $companyEmail = null;

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
            throw new \DomainException('Company ID cannot be null');
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

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setCompany($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setCompany($this);
        }

        return $this;
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

    public function getTaxIdentificationNumber(): ?string
    {
        return $this->taxIdentificationNumber;
    }

    public function setTaxIdentificationNumber(?string $taxIdentificationNumber): self
    {
        $this->taxIdentificationNumber = $taxIdentificationNumber;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): self
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getRegon(): ?string
    {
        return $this->regon;
    }

    public function setRegon(?string $regon): self
    {
        $this->regon = $regon;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): self
    {
        $this->province = $province;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $companyPhoneNumber): self
    {
        $this->phoneNumber = $companyPhoneNumber;

        return $this;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(?string $companyEmail): self
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }
}
