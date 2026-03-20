<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Const_Expr;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Const_Fetch_Node implements Const_Expr_Node
{
    use Node_Attributes;
    /** @var string class name for class constants or empty string for non-class constants */
    public string $class_name;
    public string $name;
    public function __construct(string $class_name, string $name)
    {
        $this->class_name = $class_name;
        $this->name = $name;
    }
    public function __toString(): string
    {
        if ($this->class_name === '') {
            return $this->name;
        }
        return "{$this->class_name}::{$this->name}";
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['className'], $properties['name']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}