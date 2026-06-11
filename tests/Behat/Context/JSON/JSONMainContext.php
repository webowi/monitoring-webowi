<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use App\Tests\Behat\Json\Json;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class JSONMainContext implements Context
{
    protected HeaderBag $headers;

    public function __construct(
        protected KernelInterface $kernel,
        protected ResponseState $responseState
    ) {
        $this->headers = new HeaderBag();
    }

    protected function getJson(): Json
    {
        return new Json($this->responseState->getResponse()?->getContent() ?: '');
    }

    #[AfterScenario]
    public function after(AfterScenarioScope $scope): void
    {
        $this->clearHeaders();
        $this->responseState->setResponse(null);
    }

    public function clearHeaders(): void
    {
        $this->headers = new HeaderBag();
    }

    protected function applyHeaders(Request $request): void
    {
        if ($this->headers->count()) {
            $headers = $this->headers->all();
            foreach ($headers as $key => $value) {
                /* @phpstan-ignore-next-line */
                $request->headers->set((string) $key, $value);
            }
        }
    }

    protected function processBody(PyStringNode $body = null): ?PyStringNode
    {
        if (null === $body) {
            return null;
        }
        $patternRepeat = '/generateSentence\((\d+)\)/';

        $lines = $body->getStrings();

        foreach ($lines as &$line) {
            $matches = [];
            if (preg_match($patternRepeat, $line, $matches)) {
                $line = preg_replace($patternRepeat, str_repeat('a', (int) $matches[1]), $line);
            }
        }

        return new PyStringNode($lines, $body->getLine());
    }

    protected function handleRequest(Request $request): void
    {
        $this->responseState->setResponse($this->kernel->handle($request));
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $this->responseState->getResponse());
        }
    }
}
