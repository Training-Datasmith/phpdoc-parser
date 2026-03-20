<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Nullable_Type_Node implements Type_Node
{
    use Node_Attributes;
    public Type_Node $type;
    public function __construct(Type_Node $type)
    {
        $this->type = $type;
    }
    public function __toString(): string
    {
        return '?' . $this->type;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}