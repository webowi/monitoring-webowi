<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use App\Tests\Behat\Exception\KeyValueRequiredException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class JSONRequestContext extends JSONMainContext
{
    public function __construct(KernelInterface $kernel, ResponseState $responseState)
    {
        parent::__construct($kernel, $responseState);
    }

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
        $this->headers->set('Content-Type', 'application/json');
        $this->headers->set('Accept', 'application/ld+json');

        $this->sendRequestTo($method, $url, $body, $param, $files);
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
        $this->headers->set('Content-Type', 'application/json');
        $this->headers->set('Accept', 'application/ld+json');

        $this->sendRequestToWithParameters($method, $url, $nodes);
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
     * Signs in via the real auth endpoint and stores the access token as the Authorization header
     * applied to subsequent requests.
     *
     * @Given I sign in as :email with password :password
     */
    public function signInAs(string $email, string $password): void
    {
        $body = new PyStringNode([(string) json_encode(['email' => $email, 'password' => $password])], 0);
        $this->sendJsonRequestTo('POST', '/api/v1/auth/sign-in', $body);

        /** @var array{access_token?: string} $payload */
        $payload = json_decode($this->responseState->getResponse()?->getContent() ?: '{}', true) ?? [];

        $this->headers->set('Authorization', 'Bearer ' . ($payload['access_token'] ?? ''));
    }

    /**
     * @param array<mixed, mixed> $param
     * @param array<mixed, mixed> $files
     */
    private function sendRequestTo(string $method, string $url, PyStringNode $body = null, array $param = [], array $files = []): void
    {
        $request = Request::create($url, $method, $param, [], $files, [], $this->processBody($body)?->getRaw());
        $this->applyHeaders($request);
        $this->handleRequest($request);
        unset($request);
    }

    /**
     * @throws KeyValueRequiredException
     */
    private function sendRequestToWithParameters(string $method, string $url, TableNode $nodes): void
    {
        $parameters = [];

        $nodesRowsHash = $nodes->getHash();
        foreach ($nodesRowsHash as $row) {
            if (!isset($row['key'], $row['value'])) {
                throw new KeyValueRequiredException();
            }

            $parameters[$row['key']] = $row['value'];
        }
        unset($file);

        $this->sendRequestTo($method, $url, null, $parameters);
        unset($parameters);
    }
}
