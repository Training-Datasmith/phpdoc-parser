<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Doctrine_Annotation implements Node
{
    use Node_Attributes;
    public string $name;
    /** @var list<DoctrineArgument> */
    public array $arguments;
    /**
     * @param list<DoctrineArgument> $arguments
     */
    public function __construct(string $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }
    public function __toString(): string
    {
        $arguments = implode(', ', $this->arguments);
        return $this->name . '(' . $arguments . ')';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['name'], $properties['arguments']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}