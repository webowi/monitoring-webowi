<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\TotpSecret;
use App\Identity\Infrastructure\Db\UserRepository;
use App\Kernel\EventSubscriber\TimestampableResourceInterface;
use App\Kernel\Traits\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @codeCoverageIgnore
 * @infection-ignore-all
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'validation.email.alreadyExists')]
class User implements
    UserInterface,
    PasswordAuthenticatedUserInterface,
    TwoFactorInterface,
    TimestampableResourceInterface
{
    use TimestampableTrait;

    private const TOTP_CONFIGURATION = [
        'algorithm' => TotpConfiguration::ALGORITHM_SHA1,
        'period'    => 30,
        'digits'    => 6,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    private function __construct(
        #[ORM\Column(type: 'uuid', unique: true)]
        public readonly Uuid $uuid,

        #[ORM\Column(type: 'uuid')]
        public readonly Uuid $organizationId,

        #[ORM\Embedded(class: Email::class, columnPrefix: false)]
        public readonly Email $email,

        #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
        private ?string $password = null,

        /** @var RoleEnum[] */
        #[ORM\Column(enumType: RoleEnum::class)]
        private array $roles = [RoleEnum::USER],

        #[ORM\Embedded(class: TotpSecret::class, columnPrefix: false)]
        private ?TotpSecret $totpSecret = null,

        #[ORM\Column(type: Types::STRING, length: 191, nullable: true, enumType: UserStatus::class, options: ['default' => UserStatus::UNVERIFIED],)]
        private ?UserStatus $status = UserStatus::UNVERIFIED,
    ) {}

    public static function register(
        Uuid $organizationId,
        Email $email,
        ?string $password = null,
    ): self
    {
        return new self(
            uuid: Uuid::v4(),
            organizationId: $organizationId,
            email: $email,
            password: $password,
            roles: [RoleEnum::USER],
            status: UserStatus::UNVERIFIED,
        );
    }

    public function changePassword(string $password): void
    {
        $this->password = $password;
    }

    public function enableTotp(TotpSecret $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
    }

    // --- Wymagane przez Symfony interfaces ---

    public function getUserIdentifier(): string
    {
        return $this->email->email;
    }

    public function getRoles(): array
    {
        $roles = array_map(fn(RoleEnum $role) => $role->value, $this->roles);
        $roles[] = RoleEnum::USER->value;

        return array_unique($roles);
    }

    public function getPassword(): ?string
    {
        return $this->$password;
    }

    public function eraseCredentials(): void {}

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpSecret?->isEnable() ?? false;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret->getSecret(), ...self::TOTP_CONFIGURATION);
    }

    public function verifyPassword(string $password): bool
    {
        if (null === $this->password) {
            return false;
        }

        return password_verify($password, $this->password);
    }

    public function isVerified(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
