<?php

declare(strict_types=1);

namespace App\Identity\Domain\Organization;

class OrganizationFactory
{
    public function create(
    ): Organization {
        return new Organization()
            ->setName('Default Organization');
    }
}
