<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Identifier_Type_Node implements Type_Node
{
    use Node_Attributes;
    public string $name;
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    public function __toString(): string
    {
        return $this->name;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['name']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}