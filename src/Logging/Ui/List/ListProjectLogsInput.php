<?php

declare(strict_types=1);

namespace App\Logging\Ui\List;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints as Assert;

#[Exclude]
final readonly class ListProjectLogsInput
{
    public function __construct(
        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,

        #[Assert\GreaterThanOrEqual(0)]
        public int $offset = 0,
    ) {
    }
}
