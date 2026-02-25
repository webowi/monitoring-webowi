<?php

declare(strict_types=1);

namespace App\Dashboard\Ui;

use App\Identity\Domain\Company;
use App\Identity\Domain\User;
use App\Kernel\Flasher\FlasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
final readonly class DashboardFlasherListener implements EventSubscriberInterface
{
    private const DELETE_TYPE = 'delete';

    private const EDIT_TYPE = 'edit';

    private const CREATE_TYPE = 'create';

    public function __construct(
        private FlasherInterface $flasher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['flashMessageAfterPersist'],
            AfterEntityUpdatedEvent::class   => ['flashMessageAfterUpdate'],
            AfterEntityDeletedEvent::class   => ['flashMessageAfterDelete'],
        ];
    }

    public function flashMessageAfterPersist(AfterEntityPersistedEvent $event): void
    {
        $this->generateFlashMessage($event->getEntityInstance(), self::CREATE_TYPE);
    }

    public function flashMessageAfterUpdate(AfterEntityUpdatedEvent $event): void
    {
        $this->generateFlashMessage($event->getEntityInstance());
    }

    public function flashMessageAfterDelete(AfterEntityDeletedEvent $event): void
    {
        $this->generateFlashMessage($event->getEntityInstance(), self::DELETE_TYPE);
    }

    private function getEntityName(object $entity): string
    {
        return match (get_class($entity)) {
            User::class                                   => 'account',
            Company::class                                => 'company',
            default                                       => '',
        };
    }

    private function generateFlashMessage(object $entity, string $type = self::EDIT_TYPE): void
    {
        $this->flasher
            ->success(
                sprintf('dashboard.%s.%s.success.description', $this->getEntityName($entity), $type),
                sprintf('dashboard.%s.%s.success.title', $this->getEntityName($entity), $type),
            );
    }
}
