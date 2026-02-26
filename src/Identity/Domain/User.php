<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\ValueObject\TotpSecret;
use App\Identity\Infrastructure\UserRepository;
use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\EventSubscriber\UuidResourceInterface;
use App\Kernel\Security\UserInterface;
use App\Kernel\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'validation.email.alreadyExists')]
#[Vich\Uploadable]
class User implements
    UserInterface,
    PasswordAuthenticatedUserInterface,
    TwoFactorInterface,
    TimestampableResourceInterface,
    UuidResourceInterface
{
    use TimestampableTrait;

    private const TOTP_CONFIGURATION_DIGITS = 6;

    private const TOTP_CONFIGURATION_PERIOD = 30;

    private const TOTP_CONFIGURATION = [
        'algorithm' => TotpConfiguration::ALGORITHM_SHA1,
        'period'    => self::TOTP_CONFIGURATION_PERIOD,
        'digits'    => self::TOTP_CONFIGURATION_DIGITS,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    /** @var non-empty-string */
    #[ORM\Column(type: Types::STRING, length: 180, unique: true, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Length(
        max: 180
    )]
    #[Assert\Email]
    private string $email;

    /**
     * @var string[]
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * The hashed password.
     */
    #[ORM\Column(type: Types::STRING, length: 191, nullable: false)]
    #[Assert\NotCompromisedPassword]
    private ?string $password = null;

    #[Assert\Length(max: 191)]
    private ?string $actualPassword = null;

    #[ORM\Embedded(class: TotpSecret::class, columnPrefix: false)]
    private ?TotpSecret $totpSecret = null;

    #[Vich\UploadableField(mapping: 'avatars', fileNameProperty: 'avatar', size: 'avatarSize')]
    private ?File $avatarFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(nullable: true)]
    private ?int $avatarSize = null;

    #[ORM\Column]
    private bool $isVerified = false;

    /**
     * @var Collection<int, PasswordToken> $passwordTokens
     */
    #[ORM\OneToMany(targetEntity: PasswordToken::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $passwordTokens;

    public function __construct()
    {
        $this->passwordTokens = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getEmail();
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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    /**
     * @return non-empty-string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param non-empty-string $email
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }

    /**
     * @return string[]
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $actualRoles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $actualRoles[] = RoleEnum::USER->value;

        return array_unique($actualRoles);
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getActualPassword(): ?string
    {
        return $this->actualPassword;
    }

    public function setActualPassword(string $actualPassword): self
    {
        $this->actualPassword = $actualPassword;

        return $this;
    }

    public function setTotpSecret(TotpSecret $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function disableTotpAuthentication(): void
    {
        $this->totpSecret = null;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return (bool) $this->totpSecret?->isEnable();
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return new TotpConfiguration($this->totpSecret?->getSecret() ?? '', ...self::TOTP_CONFIGURATION);
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        $this->actualPassword = null;
    }

    /**
     * @infection-ignore-all
     *
     * @codeCoverageIgnore
     */
    public function setAvatarFile(?File $avatarFile = null): void
    {
        $this->avatarFile = $avatarFile;

        if (null !== $avatarFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getAvatarFile(): ?File
    {
        return $this->avatarFile;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getAvatarUrl(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_contains($this->avatar, '/')) {
            return $this->avatar;
        }

        return sprintf('/uploads/images/avatars/%s', $this->avatar);
    }

    public function setAvatarSize(?int $avatarSize): void
    {
        $this->avatarSize = $avatarSize;
    }

    public function getAvatarSize(): ?int
    {
        return $this->avatarSize;
    }

    public function __serialize(): array
    {
        return [
            'id'       => $this->id,
            'email'    => $this->getEmail(),
            'password' => $this->password,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return in_array(RoleEnum::SUPER_ADMIN->value, $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array(RoleEnum::ADMIN->value, $this->roles, true);
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function verify(): void
    {
        $this->isVerified = true;
    }

    public function makeAdmin(): void
    {
        $this->roles[] = RoleEnum::ADMIN->value;
    }

    public function addPasswordToken(PasswordToken $passwordToken): self
    {
        if (!$this->passwordTokens->contains($passwordToken)) {
            $this->passwordTokens[] = $passwordToken;
            $passwordToken->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, PasswordToken>
     */
    public function getPasswordTokens(): Collection
    {
        return $this->passwordTokens;
    }

    public function getActiveToken(\DateTimeImmutable $now): ?PasswordToken
    {
        return array_find($this->passwordTokens->toArray(), fn ($passwordToken) => $passwordToken->isActive($now));

    }

    public function isActive(): bool
    {
        return $this->isVerified;
    }

    public function isTokenValid(string $token): bool
    {
        return array_any($this->passwordTokens->toArray(), fn ($passwordToken) => $passwordToken->isTokenSame($token)
            && $passwordToken->isActive(new \DateTimeImmutable()));

    }
}
