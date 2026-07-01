<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardRepositoryInterface;
use App\RentEquipment\Application\RentEquipmentHistoryChartService;
use App\UserLead\Domain\UserLead;
use App\UserLead\Domain\UserLeadRepositoryInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * @codeCoverageIgnore - simple repository
 *
 * @infection-ignore-all
 */
class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        private readonly RentEquipmentHistoryChartService $rentEquipmentHistoryChartService,
        private readonly UserLeadRepositoryInterface $userLeadRepository,
    ) {
    }

    public function getPopularEquipmentChart(): ?Chart
    {
        return $this->rentEquipmentHistoryChartService->providePopularEquipmentChart();
    }

    /**
     * @return UserLead[]
     */
    public function getAllLeads(): array
    {
        return $this->userLeadRepository->getAll();
    }
}
