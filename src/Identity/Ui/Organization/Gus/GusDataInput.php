<?php

declare(strict_types=1);

namespace App\Identity\Ui\Organization\Gus;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
final readonly class GusDataInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(
            max: 10
        )]
        #[Assert\Regex(pattern: '/^\d{10}$/', message: 'tin.invalid')]
        public string $tin
    ) {
    }
}
