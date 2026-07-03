<?php

declare(strict_types=1);

namespace App\Projects\Ui\CreateProject;

use App\Projects\Domain\ProjectPlatformEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProjectInput
{
    public function __construct(
        #[Assert\Type('string')]
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $name,
        #[Assert\Choice(
            callback: [ProjectPlatformEnum::class, 'values'],
        )]
        public string $platform,
    ) {}

}
