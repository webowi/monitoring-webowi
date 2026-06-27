<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use App\Tests\Behat\Exception\KeyValueRequiredException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

class JSONRequestContext extends JSONMainContext
{
    /**
     * Sends a JSON HTTP request.
     *
     * @Given I send a :method JSON request to :url
     *
     * @param array<mixed, mixed> $param
     * @param array<mixed, mixed> $files
     */
    public function sendJsonRequestTo(string $method, string $url, ?PyStringNode $body = null, array $param = [], array $files = []): void
    {
        $this->sendJsonRequest($method, $url, $body, $param, $files);
    }

    /**
     * Sends a JSON HTTP request with a body.
     *
     * @Given I send a :method JSON request :times times to :url with body:
     */
    public function sendJsonRequestTimesToWithBody(string $method, int $times, string $url, PyStringNode $body): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->sendJsonRequestTo($method, $url, $body);
        }
    }

    /**
     * Sends a JSON HTTP request with parameters.
     *
     * @Given I send a :method JSON request to :url with parameters:
     */
    public function sendJsonRequestToWithParameters(string $method, string $url, TableNode $nodes): void
    {
        $this->sendJsonRequest($method, $url, null, $this->parametersFromTable($nodes));
    }

    /**
     * Sends a JSON HTTP request with a body.
     *
     * @Given I send a :method JSON request to :url with body:
     */
    public function sendJsonRequestToWithBody(string $method, string $url, PyStringNode $body): void
    {
        $this->sendJsonRequestTo($method, $url, $body);
    }

    /**
     * Sets an arbitrary request header applied to the next request.
     *
     * @Given I set the header :name to :value
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers->set($name, $value);
    }

    /**
     * @return array<string, string>
     *
     * @throws KeyValueRequiredException
     */
    private function parametersFromTable(TableNode $nodes): array
    {
        $parameters = [];

        foreach ($nodes->getHash() as $row) {
            if (!isset($row['key'], $row['value'])) {
                throw new KeyValueRequiredException();
            }

            $parameters[$row['key']] = $row['value'];
        }

        return $parameters;
    }
}
