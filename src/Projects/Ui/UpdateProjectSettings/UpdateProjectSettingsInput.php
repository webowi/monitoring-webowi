<?php

declare(strict_types=1);

namespace App\Projects\Ui\UpdateProjectSettings;

use App\Projects\Domain\ProjectPlatformEnum;
use App\Projects\Domain\ProjectStatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProjectSettingsInput
{
    public function __construct(
        #[Assert\Type('string')]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 500)]
        public ?string $name = null,
        #[Assert\Choice(
            callback: [ProjectPlatformEnum::class, 'values'],
        )]
        public ?string $platform = null,
        #[Assert\Choice(
            callback: [ProjectStatusEnum::class, 'values'],
        )]
        public ?string $status = null,
    ) {}
}
