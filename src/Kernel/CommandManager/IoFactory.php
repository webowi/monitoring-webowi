<?php

declare(strict_types=1);

namespace App\Kernel\CommandManager;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
class IoFactory implements IoFactoryInterface
{
    public function create(InputInterface $input, OutputInterface $output): SymfonyStyle
    {
        return new SymfonyStyle($input, $output);
    }
}
