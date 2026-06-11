<?php

declare(strict_types=1);

namespace App\Tests\Behat\Json;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class Json
{
    /**
     */
    protected $content;

    public function __construct(string $content, private bool $associative = false)
    {
        $this->content = $this->decode($content);
    }

    /**
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     */
    public function read(string $expression, PropertyAccessor $accessor)
    {
        if (\is_array($this->content)) {
            $expression = (string) \preg_replace('/^root/', '', $expression);
        } else {
            $expression = (string) \preg_replace('/^root./', '', $expression);
        }

        // If root asked, we return the entire content
        if (\strlen(\trim($expression)) <= 0) {
            return $this->content;
        }

        /** @var array<mixed>|object $content */
        $content = $this->content;

        return $accessor->getValue($content, $expression);
    }

    public function encode(bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (true === $pretty && \defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return \json_encode($this->content, JSON_THROW_ON_ERROR | $flags);
    }

    public function __toString()
    {
        return $this->encode(false);
    }

    /**
     */
    private function decode(string $content)
    {
        return json_decode($content, $this->associative, 512, JSON_THROW_ON_ERROR);
    }
}
