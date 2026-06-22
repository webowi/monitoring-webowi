<?php

declare(strict_types=1);

namespace App\Kernel\EventSubscriber;

use App\Kernel\Clock\ClockInterface;
use App\Kernel\Security\CurrentUserFetcher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::prePersist, priority: 0, connection: 'default')]
#[AsDoctrineListener(event: Events::preUpdate, priority: 0, connection: 'default')]
#[AsDoctrineListener(event: Events::preRemove, priority: 0, connection: 'default')]
final readonly class TimestampableSubscriber
{
    public function __construct(
        private CurrentUserFetcher $currentUserFetcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {}

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TimestampableResourceInterface) {
            return;
        }

        $dateTimeNow = $this->getDateTimeNow();

        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt($dateTimeNow);
        }

        $entity->setUpdatedAt($dateTimeNow);

        try {
            $user = $this->currentUserFetcher->fetchUser();

            if (null === $entity->getCreatedBy()) {
                $entity->setCreatedBy($user->getUserIdentifier());
            }

            $entity->setUpdatedBy($user->getUserIdentifier());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Setting user data for resource failed.',
                [
                    'exception' => $exception,
                    'class'     => __CLASS__,
                ]
            );
        }
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TimestampableResourceInterface) {
            return;
        }

        $entity->setUpdatedAt($this->getDateTimeNow());

        try {
            $user = $this->currentUserFetcher->fetchUser();
            $entity->setUpdatedBy($user->getUserIdentifier());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Setting user data for resource failed.',
                [
                    'exception' => $exception,
                    'class'     => __CLASS__,
                ]
            );
        }
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TimestampableResourceInterface) {
            return;
        }

        $entity->setDeletedAt($this->getDateTimeNow());

        try {
            $user = $this->currentUserFetcher->fetchUser();
            $entity->setDeletedBy($user->getUserIdentifier());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Setting user data for resource failed.',
                [
                    'exception' => $exception,
                    'class'     => __CLASS__,
                ]
            );
        }
    }

    private function getDateTimeNow(): \DateTimeImmutable
    {
        $now = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
        if (false === $now) {
            throw new \LogicException('Getting current date and time failed.');
        }

        return $this->clock->dateTime($now);
    }
}
