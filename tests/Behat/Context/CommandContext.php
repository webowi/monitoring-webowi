<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class CommandContext implements Context
{
    private Application $application;

    private BufferedOutput $output;

    public function __construct(KernelInterface $kernel)
    {
        $this->output = new BufferedOutput();
        $this->application = new Application($kernel);
    }

    /**
     * @Then I run command :command with arguments:
     *
     * @throws \JsonException
     */
    public function commandWithParams(string $command, TableNode $arguments): void
    {
        $commandParameters = [
            'command' => $command,
        ];

        foreach ($arguments->getRowsHash() as $node => $value) {
            $commandParameters[$this->prepareAsFlag($node)] = $value;
        }

        $this->application->doRun(new ArrayInput($commandParameters), $this->output);
    }

    /**
     * @Then I run command :command
     */
    public function command(string $command): void
    {
        $this->application->doRun(new ArrayInput(['command' => $command]), $this->output);
    }

    private function prepareAsFlag(string $value): string
    {
        return str_starts_with($value, '--') ? $value : \sprintf('--%s', $value);
    }
}
