<?php

declare(strict_types=1);

namespace App\Tests\Behat\Faker\Provider;

use App\Identity\Domain\Organization\Organization;
use App\Identity\Domain\User\User;
use App\Identity\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;

class TechnicalUserProvider
{
    private const string TECHNICAL_USER_EMAIL = 'technical@user.com';
    private const string TECHNICAL_USER_PASS = 'password!1!';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHashProvider $passwordHashProvider,
    ) {}

    public function provide(): TechnicalUserContext
    {
        $organization = $this->createOrganization();

        try {
            $user = User::register($organization->getUuid(), new Email(self::TECHNICAL_USER_EMAIL));
            $hashedPass = $this->passwordHashProvider->hashPassword($user, self::TECHNICAL_USER_PASS);
            $user->changePassword($hashedPass);
            $user->activate();

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            throw new \RuntimeException(\sprintf('Failed to create technical user: %s', $exception->getMessage()));
        }

        return new TechnicalUserContext(self::TECHNICAL_USER_EMAIL, self::TECHNICAL_USER_PASS);
    }

    private function createOrganization(): Organization
    {
        try {
            $organization = Organization::register('name');
            $this->entityManager->persist($organization);
            $this->entityManager->flush();

            return $organization;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(\sprintf('Failed to create organization: %s', $exception->getMessage()));
        }
    }
}
