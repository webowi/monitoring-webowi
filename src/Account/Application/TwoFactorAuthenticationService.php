<?php

declare(strict_types=1);

namespace App\Account\Application;

use App\Account\Application\Exception\CannotChange2FaStateException;
use App\Account\Domain\User;
use App\Account\Domain\UserRepositoryInterface;
use App\Account\Domain\ValueObject\TotpSecret;
use App\Kernel\Security\UserInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class TwoFactorAuthenticationService
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws CannotChange2FaStateException
     */
    public function enable2fa(UserInterface $user): void
    {
        try {
            if (false === $user->isTotpAuthenticationEnabled()) {
                $user->setTotpSecret(new TotpSecret($this->totpAuthenticator->generateSecret()));

                $this->userRepository->save($user);
            }
        } catch (\Throwable $exception) {
            $this->logger
                ->error('An error occurred while enabling 2FA for the user.', ['exception' => $exception]);

            throw new CannotChange2FaStateException();
        }
    }

    /**
     * @throws CannotChange2FaStateException
     */
    public function disable2fa(User $user): void
    {
        try {
            if (true === $user->isTotpAuthenticationEnabled()) {
                $user->setTotpSecret(new TotpSecret(null));

                $this->userRepository->save($user);
            }
        } catch (\Throwable $exception) {
            $this->logger
                ->error('An error occurred while enabling 2FA for the user.', ['exception' => $exception]);

            throw new CannotChange2FaStateException();
        }
    }

    /**
     * @throws CannotChange2FaStateException
     */
    public function generate2faQrCode(User $user): ResultInterface
    {
        $this->enable2fa($user);
        $qrCodeContent = $this->totpAuthenticator->getQRContent($user);

        try {
            return (new Builder(
                data: $qrCodeContent
            ))->build();
        } catch (ValidationException $e) {
            $this->logger->error('An error occurred while generating 2FA QR code.', ['exception' => $e]);

            throw new CannotChange2FaStateException();
        }
    }
}
