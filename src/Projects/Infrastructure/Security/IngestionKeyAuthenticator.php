<?php

declare(strict_types=1);

namespace App\Projects\Infrastructure\Security;

use App\Projects\Domain\IngestionKeyRepositoryInterface;
use App\Projects\Domain\ProjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class IngestionKeyAuthenticator extends AbstractAuthenticator
{
    private const string HEADER_NAME = 'X-Ingestion-Key';

    public function __construct(
        private readonly IngestionKeyHasher $hasher,
        private readonly IngestionKeyRepositoryInterface $ingestionKeyRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $presentedKey = (string) $request->headers->get(self::HEADER_NAME, '');

        $ingestionKey = $this->ingestionKeyRepository->findOneActiveByKeyHash($this->hasher->hash($presentedKey));

        if (null === $ingestionKey) {
            throw new InvalidIngestionKeyException();
        }

        $project = $this->projectRepository->getById($ingestionKey->getProjectId());

        if (null === $project) {
            throw new InvalidIngestionKeyException();
        }

        $ingestionKey->markUsedNow();
        $this->entityManager->flush();

        $principal = new IngestionPrincipal($project, $ingestionKey);

        return new SelfValidatingPassport(
            new UserBadge((string) $project->getUuid(), static fn (): IngestionPrincipal => $principal),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
