<?php

declare(strict_types=1);

namespace App\Tests\Behat\Json;

use Exception;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class Inspector
{
    private PropertyAccessor $accessor;

    public function __construct(private string $evaluationMode = 'javascript')
    {
        $this->accessor = new PropertyAccessor(0, 1 | 2);
    }

    /**
     */
    public function evaluate(Json $json, string $expression)
    {
        if ('javascript' === $this->evaluationMode) {
            $expression = \str_replace('->', '.', $expression);
        }

        try {
            return $json->read($expression, $this->accessor);
        } catch (Exception) {
            throw new \Exception("Failed to evaluate expression '$expression'");
        }
    }

    public function validate(Json $json, JsonSchema $schema): bool
    {
        $validator = new Validator();

        $resolver = new SchemaStorage(new UriRetriever(), new UriResolver());
        $schema->resolve($resolver);

        return $schema->validate($json, $validator);
    }
}
