<?php

declare(strict_types=1);

namespace App\Dashboard\Ui;

use App\Account\Domain\RoleEnum;
use App\Account\Domain\User;
use App\Account\Ui\AccountCrudController;
use App\Kernel\Security\MultiplyRolesExpression;
use App\Kernel\Security\UserInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @method UserInterface getUser()
 *
 * @codeCoverageIgnore - this is a CRUD controller
 *
 * @infection-ignore-all
 */
#[IsGranted(new MultiplyRolesExpression(RoleEnum::ADMIN, RoleEnum::SUPER_ADMIN, RoleEnum::MODERATOR))]
class DashboardCrudController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    #[Route('/', name: 'main')]
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Jeśli chcesz od razu wejść na custom dashboard:
        return $this->render('dashboard/main/home.html.twig', [
            'page_title' => 'Monitoring',
            'kpis'       => [
                ['label' => 'Aplikacje', 'value' => '—', 'hint' => 'podłączysz później'],
                ['label' => 'Błędy 24h', 'value' => '—', 'hint' => 'krytyczne / ostrzeżenia'],
                ['label' => 'Średni czas reakcji', 'value' => '—', 'hint' => 'SLA / MTTR'],
                ['label' => 'Ostatni ingest', 'value' => '—', 'hint' => 'ostatni log'],
            ],
            'critical' => [],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('monitoring-webowi')
            ->setFaviconPath('/images/favicon.ico')
            ->setTranslationDomain('messages')
            ->setDefaultColorScheme('dark')
            ->disableDarkMode(false);
    }

    /**
     * @param User $user
     *
     * @infection-ignore-all
     */
    public function configureUserMenu($user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setAvatarUrl($user->getAvatarUrl())
            ->addMenuItems([
                MenuItem::linkToCrud('dashboard.account.section.title', 'fa fa-user-cog', User::class)
                    ->setController(AccountCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($user->getId() ?: 0),
            ]);
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(
                Crud::PAGE_DETAIL,
                Action::EDIT,
                static fn (Action $action) => $action->setIcon('fa fa-edit')
            )
            ->update(
                Crud::PAGE_DETAIL,
                Action::INDEX,
                static fn (Action $action) => $action->setIcon('fa fa-list')
            );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('dashboard.panel.projects');
    }
}
