<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context\JSON;

use App\Tests\Behat\Exception\JsonNodeNotEqualException;
use App\Tests\Behat\Json\Inspector;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\KernelInterface;

class JSONResponseContext extends JSONMainContext
{
    private Inspector $inspector;

    public function __construct(
        KernelInterface $kernel,
        ResponseState $responseState,
        string $evaluationMode = 'javascript',
    ) {
        $this->inspector = new Inspector($evaluationMode);
        parent::__construct($kernel, $responseState);
    }

    private function theJsonNodeShouldHaveValue(string $node, string $text, bool $strict = true): void
    {
        $value = match ($text) {
            'NULL'  => null,
            'TRUE'  => true,
            'FALSE' => false,
            default => $text,
        };

        $actual = $this->inspector->evaluate($this->getJson(), $node);

        $compare = $strict ? $actual === $value : str_contains((string) $actual, $value);

        if (\is_string($value)) {
            if (!$compare) {
                throw new JsonNodeNotEqualException($actual);
            }
        } elseif (!$compare) {
            throw new JsonNodeNotEqualException($actual);
        }
        unset($actual, $value);
    }

    /**
     * @Then the JSON node :node should be equal to :text
     *
     * @throws JsonNodeNotEqualException
     */
    public function theJsonNodeShouldBeEqualTo(string $node, mixed $text): void
    {
        $this->theJsonNodeShouldHaveValue($node, $text, false);
    }

    /**
     * @Then the JSON hydra description should be equal to :text
     *
     * @throws JsonNodeNotEqualException
     */
    public function theJsonHydraDescriptionShouldBeEqualTo(string $text): void
    {
        $this->theJsonNodeShouldHaveValue('hydra:description', $text);
    }

    /**
     * @Then the JSON hydra description should contain :text
     *
     * @throws JsonNodeNotEqualException
     */
    public function theJsonHydraDescriptionShouldContain(string $text): void
    {
        $this->theJsonNodeShouldHaveValue('hydra:description', $text, false);
    }

    /**
     * Checks, that given JSON nodes contains values.
     *
     * @Then the JSON nodes should contain:
     */
    public function theJsonNodesShouldContain(TableNode $nodes): void
    {
        $nodesRowsHash = $nodes->getRowsHash();
        foreach ($nodesRowsHash as $node => $text) {
            $this->theJsonNodeShouldContain($node, $text);
        }
        unset($nodesRowsHash);
    }

    /**
     * Checks, that given JSON node contains given value.
     *
     * @Then the JSON node :node should contain :text
     */
    public function theJsonNodeShouldContain(string $node, string $text): void
    {
        $actual = $this->inspector->evaluate($this->getJson(), $node);
        $message = \sprintf('Tested node was "%s":', $node);

        switch ($text) {
            case 'NOT NULL':
                Assert::assertNotNull($actual, $message);
                break;
            case 'NULL':
                Assert::assertNull($actual, $message);
                break;
            case 'FALSE':
                Assert::assertFalse($actual, $message);
                break;
            case 'TRUE':
                Assert::assertTrue($actual, $message);
                break;
            default:
                if (\is_string($actual)) {
                    Assert::assertStringContainsString($text, $actual, $message);
                }
        }
        unset($actual, $message);
    }

    /**
     * @Then the response status code should be :code
     */
    public function assertResponseStatus(int $code): void
    {
        Assert::assertSame($code, $this->responseState->getResponse()?->getStatusCode());
    }

    /**
     * @Then the response should redirect to :redirect
     */
    public function assertResponseContains(string $redirect): void
    {
        Assert::assertStringContainsString($redirect, $this->responseState->getResponse()->getContent());
    }

    /**
     * @Then print last JSON response
     */
    public function printLastJsonResponse(): void
    {
        echo $this->getJson()->encode();
    }

    /**
     * @Then print last response
     */
    public function printLastResponse(): void
    {
        echo $this->responseState->getResponse() ? $this->responseState->getResponse()->getContent() : '';
    }

    /**
     * Checks whether the JSON node is an array containing given number of elements.
     *
     * @Then the JSON node :node should be an array with :number elements
     */
    public function theJsonNodeShouldBeArrayWithSpecificCount(string $node, string $number): void
    {
        $actual = $this->inspector->evaluate($this->getJson(), $node);
        Assert::assertIsArray($actual);
        Assert::assertCount((int) $number, (array) $actual);
        unset($actual);
    }

    /**
     * Checks, that given nested JSON node is equal to given value.
     *
     * @Then the JSON node :node with index :index nested in array :parentNode should be equal to :text
     */
    public function theNestedJsonNodeShouldBeEqualTo(string $node, string|int $index, string $parentNode, string $text): void
    {
        $value = match ($text) {
            'NULL'  => null,
            'TRUE'  => true,
            'FALSE' => false,
            default => $text,
        };
        $parentActual = $this->inspector->evaluate($this->getJson(), $parentNode);

        $element = match ($index) {
            'first' => $parentActual[0],
            'last'  => $parentActual[\count($parentActual) - 1],
            default => $parentActual[$index],
        };

        if (str_contains($node, '.')) {
            [$node1, $node2] = explode('.', $node);
            $actual = $element->$node1->$node2;
        } else {
            $actual = $element->$node;
        }

        if (\is_string($value)) {
            if ((string) $actual !== $value) {
                throw new JsonNodeNotEqualException($actual);
            }
        } elseif ((string) $actual !== $value) {
            throw new JsonNodeNotEqualException($actual);
        }
        unset($actual, $value);
    }
}
