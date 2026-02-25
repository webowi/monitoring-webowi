<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\CreateNewUserException;
use App\Identity\Domain\CompanyFactory;
use App\Identity\Domain\User;
use App\Identity\Domain\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CreateAccountHandler
{
    public function __construct(
        private readonly UserFactory                      $userFactory,
        private readonly CompanyFactory                   $companyFactory,
        private readonly LoggerInterface                  $logger,
        private readonly EntityManagerInterface           $entityManager,
    ) {
    }

    /**
     * @throws CreateNewUserException
     */
    public function create(CreateAccountCommand $command): User
    {
        $this->entityManager->beginTransaction();
        try {
            $company = $this->companyFactory->create($command->email);
            $user = $this->userFactory->create($command->email, $command->password, $company);

            $this->entityManager->persist($company);
            $this->entityManager->persist($user);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                'An error occurred while creating a new account.',
                [
                    'exception' => $e,
                    'email'     => $command->email,
                    'class'     => __CLASS__,
                ]
            );

            throw new CreateNewUserException();
        }

        return $user;
    }
}
