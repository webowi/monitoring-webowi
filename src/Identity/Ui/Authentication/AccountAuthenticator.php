<?php

declare(strict_types=1);

namespace App\Identity\Ui\Authentication;

use App\Identity\Application\AccountAuthenticatorService;
use App\Identity\Ui\Exception\EmailRequiredException;
use App\Identity\Ui\Exception\PasswordRequiredException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AccountAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly AccountAuthenticatorService $accountAuthenticator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $this->getLoginUrl($request) === $request->getPathInfo();
    }

    /**
     * @throws EmailRequiredException
     * @throws PasswordRequiredException
     */
    public function authenticate(Request $request): Passport
    {
        $loginFormData = $request->request->all('login_form');
        $email = $loginFormData['email'] ?? null;
        $password = $loginFormData['password'] ?? null;

        if (!is_string($email)) {
            throw new EmailRequiredException();
        }

        if (!is_string($password)) {
            throw new PasswordRequiredException();
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->getPanelDashboardUrl());
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->accountAuthenticator->getLoginUrl();
    }

    private function getPanelDashboardUrl(): string
    {
        return $this->accountAuthenticator->getPanelDashboardUrl();
    }
}
