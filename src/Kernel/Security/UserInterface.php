<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Identity\Domain\Company;
use App\Identity\Domain\ValueObject\TotpSecret;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;
use Symfony\Component\Uid\Uuid;

interface UserInterface extends BaseUserInterface
{
    public function getUuid(): ?Uuid;

    public function getEmail(): ?string;

    public function isSuperAdmin(): bool;

    public function getCompany(): Company;

    public function isTotpAuthenticationEnabled(): bool;

    public function setTotpSecret(TotpSecret $totpSecret): self;
}
