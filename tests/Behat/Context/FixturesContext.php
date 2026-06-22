<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;

final class FixturesContext implements Context
{
    private const EXT = '.yml';

    private const PATH = '/tests/Behat/Fixtures/';

    public function __construct(
        private LoaderInterface $ORMLoader,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {}

    /**
     * @Given the following fixtures are loaded from the files:
     */
    public function thereAreSeveralFixtures(TableNode $fixtures): void
    {
        $fixturesFiles = [];
        $fixturesFileRows = $fixtures->getRows();
        foreach ($fixturesFileRows as $fixturesFileRow) {
            $fixturesFiles[] = \sprintf('%s%s%s%s', $this->projectDir, self::PATH, $fixturesFileRow[0], self::EXT);
        }

        $this->ORMLoader->load($fixturesFiles, [], [], PurgeMode::createTruncateMode());
        $this->entityManager->clear();
        unset($fixturesFiles, $fixturesFileRows);
    }
}
