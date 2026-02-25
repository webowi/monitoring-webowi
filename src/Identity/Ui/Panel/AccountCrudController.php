<?php

declare(strict_types=1);

namespace App\Identity\Ui\Panel;

use App\Identity\Application\Exception\CannotChange2FaStateException;
use App\Identity\Application\Password\DashboardPasswordService;
use App\Identity\Application\TwoFactorAuthenticationService;
use App\Identity\Domain\RoleEnum;
use App\Identity\Domain\User;
use App\Kernel\EventSubscriber\AbstractBaseCrudController;
use App\Kernel\Flasher\FlasherInterface;
use App\Kernel\Form\Field\VichImageField;
use App\Kernel\Security\MultiplyRolesExpression;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new MultiplyRolesExpression(RoleEnum::ADMIN, RoleEnum::SUPER_ADMIN, RoleEnum::MODERATOR))]
class AccountCrudController extends AbstractBaseCrudController
{
    public function __construct(
        private readonly DashboardPasswordService $dashboardPasswordService,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        /** @phpstan-ignore-next-line  */
        $fa2Action = true === $this->getUser()?->isTotpAuthenticationEnabled() ? $this->createDisable2FaAction() : $this->createEnable2FaAction();

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->add(Crud::PAGE_EDIT, $fa2Action);
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->getUser();

        yield FormField::addTab('dashboard.panel.mainInformation');
        yield FormField::addFieldset('dashboard.account.section.title')
            ->setCssClass('col-md-6 form-panel')
            ->collapsible();
        yield EmailField::new('email')
            ->setRequired(true)
            ->onlyOnForms();
        yield VichImageField::new('avatarFile', 'dashboard.account.avatar.title')
            ->setDownloadUri('public/uploads/images/avatars')
            ->setImageUri(null)
            ->setUploadedFileNamePattern(sprintf('%s-[slug]-[timestamp].[extension]', $user?->getUuid()->toBinary()));
        yield FormField::addFieldset('dashboard.account.changePassword.title')
            ->setCssClass('col-md-6 form-panel')
            ->collapsible();
        yield TextField::new('actualPassword')
            ->setLabel('dashboard.account.actualPassword.title')
            ->setFormType(PasswordType::class)
            ->setRequired(false)
            ->onlyOnForms();
        yield TextField::new('password')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type'           => PasswordType::class,
                'first_options'  => ['label' => 'dashboard.account.newPassword.title'],
                'second_options' => ['label' => 'dashboard.account.confirmPassword.title'],
                'mapped'         => false,
            ])
            ->setRequired(false)
            ->onlyOnForms()
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_EDIT, 'dashboard.account.title')
            ->setSearchFields(null);
    }

    /**
     * todo make unit tests or rebuilt password change, cannot make createEditFormBuilder tests.
     *
     * @infection-ignore-all
     *
     * @codeCoverageIgnore
     */
    public function createEditFormBuilder(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context,
    ): FormBuilderInterface {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        return $this->dashboardPasswordService->provideChangePasswordEventListener($formBuilder, $context);
    }

    private function createEnable2FaAction(): Action
    {
        return Action::NEW('enable2Fa')
            ->setLabel('dashboard.account.2fa.enable.title')
            ->setCssClass('btn btn-success')
            ->setIcon('fa fa-lock')
            ->linkToCrudAction('enable2Fa');
    }

    private function createDisable2FaAction(): Action
    {
        return Action::NEW('disable2da')
            ->setLabel('dashboard.account.2fa.disable.title')
            ->setCssClass('btn btn-danger')
            ->setIcon('fa fa-lock-open')
            ->linkToCrudAction('disable2Fa');
    }

    public function enable2Fa(FlasherInterface $flasher): Response
    {
        try {

            $user = $this->getUser();

            $this->twoFactorAuthenticationService->enable2fa($user);

            $flasher
                ->success('dashboard.account.2fa.turnOn.success.description', 'dashboard.account.2fa.turnOn.success.title');
        } catch (CannotChange2FaStateException) {
            $flasher
                ->error('dashboard.account.2fa.turnOn.error.description', 'dashboard.account.2fa.turnOn.error.title');
        }

        return $this->render('dashboard/authentication/2fa/enable2fa.html.twig');
    }

    public function disable2Fa(FlasherInterface $flasher): RedirectResponse
    {
        try {
            $user = $this->getUser();
            $this->twoFactorAuthenticationService->disable2fa($user);

            $flasher
                ->success('dashboard.account.2fa.turnOff.success.description', 'dashboard.account.2fa.turnOff.success.title');
        } catch (CannotChange2FaStateException) {
            $flasher
                ->error('dashboard.account.2fa.turnOff.error.description', 'dashboard.account.2fa.turnOff.error.title');
        }

        $referer = $this->adminUrlGenerator
            ->setController(AccountCrudController::class)
            ->setAction(Action::EDIT)
            ->generateUrl();

        return $this->redirect($referer);
    }
}
