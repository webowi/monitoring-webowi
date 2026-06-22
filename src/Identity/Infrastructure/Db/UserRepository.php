<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Db;

use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\Email;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array<string, mixed> $criteria, array<string, string|null> $orderBy = null, $limit = null, $offset = null)
 *
 * @codeCoverageIgnore Simply repository
 *
 * @infection-ignore-all
 */
class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getByEmail(Email $email): ?User
    {
        return $this->findOneBy(['email.email' => $email->email]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->changePassword($newHashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->save($user);
    }
}
