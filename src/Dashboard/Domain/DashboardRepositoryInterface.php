<?php

declare(strict_types=1);

namespace App\Dashboard\Domain;

use App\UserLead\Domain\UserLead;
use Symfony\UX\Chartjs\Model\Chart;

interface DashboardRepositoryInterface
{
    public function getPopularEquipmentChart(): ?Chart;

    /**
     * @return UserLead[]
     */
    public function getAllLeads(): array;
}
