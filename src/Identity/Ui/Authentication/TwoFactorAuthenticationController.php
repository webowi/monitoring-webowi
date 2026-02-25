<?php

declare(strict_types=1);

namespace App\Identity\Ui\Authentication;

use App\Identity\Application\Exception\CannotChange2FaStateException;
use App\Identity\Application\TwoFactorAuthenticationService;
use App\Identity\Domain\RoleEnum;
use App\Identity\Ui\AbstractBaseController;
use App\Kernel\Security\MultiplyRolesExpression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TwoFactorAuthenticationController extends AbstractBaseController
{
    /**
     * @throws CannotChange2FaStateException
     */
    #[Route(path: '/dashboard/account/authenticate/2fa/qr-code', name: 'app_qr_code')]
    #[IsGranted(new MultiplyRolesExpression(RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN, RoleEnum::MODERATOR))]
    public function authenticatorQrCode(TwoFactorAuthenticationService $twoFactorAuthenticationService): Response
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new \LogicException('User not found, to disable2fa authorization.');
        }

        $result = $twoFactorAuthenticationService->generate2faQrCode($user);

        return new Response($result->getString(), 200, ['Content-Type' => 'image/png']);
    }
}
