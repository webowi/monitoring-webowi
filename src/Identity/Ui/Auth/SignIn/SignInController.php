<?php

declare(strict_types=1);

namespace App\Identity\Ui\Auth\SignIn;

use App\Identity\Application\Auth\SignIn\AccountNotVerifiedException;
use App\Identity\Application\Auth\SignIn\InvalidCredentialsException;
use App\Identity\Application\Auth\SignIn\SignInCommand;
use App\Identity\Application\Auth\SignIn\SignInHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/auth/sign-in', name: 'auth_sign_in', methods: ['POST'])]
final class SignInController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SignInHandler $signInHandler,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(#[MapRequestPayload] SignInInput $input): JsonResponse
    {
        $this->validator->validate($input);

        $command = new SignInCommand(
            email: $input->email,
            password: $input->password,
        );

        try {
            $signInResult = $this->signInHandler->handle($command);
        } catch (AccountNotVerifiedException|InvalidCredentialsException $e) {
            return new JsonResponse(
                data: ['error' => $e->getMessage()],
                status: Response::HTTP_BAD_REQUEST,
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                \sprintf('Error during sign-in process for %s', $input->email),
                ['exception' => $e]
            );

            return new JsonResponse(
                data: ['error' => 'Unexpected error occurred. Please try again later.'],
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(
            data: $signInResult->toArray(),
            status: Response::HTTP_OK,
        );
    }
}
