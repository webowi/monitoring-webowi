<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use Behat\Gherkin\Node\PyStringNode;

final class AuthenticationContext extends JSONMainContext
{
    /**
     * @Given I am authorized as :email with password :password
     */
    public function iAmAuthorized(string $email, string $password): void
    {
        $this->signInAs($email, $password);
    }

    /**
     * @Given I am unauthorized
     */
    public function iAmUnauthorized(): void
    {
        $this->headers->remove('Authorization');
    }

    /**
     * Signs in via the real auth endpoint and stores the access token as the Authorization header
     * applied to subsequent requests.
     *
     * @Given I sign in as :email with password :password
     *
     * @throws \JsonException
     */
    public function signInAs(string $email, string $password): void
    {
        $this->headers->set('Authorization', \sprintf('Bearer %s', $this->generateAccessToken($email, $password)));
    }

    private function generateAccessToken(string $email, string $password): string
    {
        $body = new PyStringNode([
            json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        ], 0);

        $this->sendJsonRequest('POST', '/api/v1/auth/sign-in', $body);

        $response = $this->responseState->getResponse();

        if (null === $response || 200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Could not sign in technical user, reason: %s', $response?->getContent() ?? 'No response'));
        }

        $payload = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!isset($payload['access_token']) || !\is_string($payload['access_token'])) {
            throw new \RuntimeException('Auth response does not contain access_token.');
        }

        return $payload['access_token'];
    }
}
