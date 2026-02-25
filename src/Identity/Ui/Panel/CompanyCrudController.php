<?php

declare(strict_types=1);

namespace App\Identity\Ui\Panel;

use App\Identity\Domain\Company;
use App\Identity\Domain\RoleEnum;
use App\Kernel\EventSubscriber\AbstractBaseCrudController;
use App\Kernel\Security\MultiplyRolesExpression;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new MultiplyRolesExpression(RoleEnum::ADMIN, RoleEnum::SUPER_ADMIN, RoleEnum::MODERATOR))]
class CompanyCrudController extends AbstractBaseCrudController
{
    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_NEW, 'dashboard.company.title')
            ->setPageTitle(Crud::PAGE_EDIT, 'dashboard.company.title')
            ->setSearchFields(null)
            ->addFormTheme('dashboard/company/form_theme.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, fn (Action $action) => $action->setLabel('dashboard.save'))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn (Action $action) => $action->setLabel('dashboard.save'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('dashboard.panel.mainInformation');
        yield FormField::addFieldset('dashboard.panel.mainInformation')
            ->setIcon('fa fa-house')
            ->collapsible();
        yield TextField::new('uuid')
            ->setLabel('uuid')
            ->onlyOnForms()
            ->setPermission(RoleEnum::SUPER_ADMIN->value)
            ->setDisabled();
        yield TextField::new('taxIdentificationNumber')
            ->setLabel('dashboard.company.taxIdentificationNumber.title')
            ->setRequired(true)
            ->setMaxLength(10)
            ->setFormTypeOptions([
                'block_name' => 'gus_nip',
            ])
            ->onlyOnForms();
        yield TextField::new('name')
            ->setLabel('dashboard.company.name.title')
            ->setRequired(true)
            ->onlyOnForms();
        yield TextField::new('province')
            ->setLabel('dashboard.company.province.title')
            ->onlyOnForms();
        yield TextField::new('city')
            ->setLabel('dashboard.company.city.title')
            ->onlyOnForms();
        yield TextField::new('zipCode')
            ->setLabel('dashboard.company.zipCode.title')
            ->onlyOnForms();
        yield TextField::new('street')
            ->setLabel('dashboard.company.street.title')
            ->onlyOnForms();
    }
}
