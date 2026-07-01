<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateAccount;

use App\Identity\Application\CreateOrganization\CreateOrganizationCommand;
use App\Identity\Application\CreateOrganization\CreateOrganizationHandler;
use App\Identity\Domain\User\UserExistException;
use App\Identity\Domain\User\UserFactory;
use App\Identity\Domain\User\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAccountHandler
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CreateOrganizationHandler $createOrganizationHandler,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function handle(CreateAccountCommand $command): void
    {
        $this->entityManager->beginTransaction();
        try {
            $organization = $this->createOrganizationHandler->handle(new CreateOrganizationCommand($command->organizationName));

            $user = $this->userFactory->create(
                organizationId: $organization->uuid,
                email: $command->email,
            );

            $hashedPassword = $this->passwordHasher->hashPassword($user, $command->plainPassword);
            $user->changePassword($hashedPassword);
            $user->activate();
            $this->userRepository->save($user);
            $this->entityManager->commit();
        } catch (UserExistException) {
            $this->entityManager->rollback();
            throw new UserExistException();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }
    }
}
