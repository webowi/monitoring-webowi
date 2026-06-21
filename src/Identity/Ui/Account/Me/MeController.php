<?php

declare(strict_types=1);

namespace App\Identity\Ui\Account\Me;

use App\Identity\Application\Account\GetMe\GetMeHandler;
use App\Identity\Application\Account\GetMe\GetMeQuery;
use App\Identity\Domain\Account\AccountId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/me', name: 'identity_me', methods: ['GET'])]
final readonly class MeController
{
    public function __construct(
        private Security $security,
//        private GetMeHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return new JsonResponse(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

//        $result = $this->handler->handle(
//            new GetMeQuery(
//                accountId: AccountId::fromString($user->getUserIdentifier()),
//            )
//        );

        $result = [
            'id' => $user->getUserIdentifier(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        return new JsonResponse(
            $result,
            Response::HTTP_OK,
        );
    }
}