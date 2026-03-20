<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Offset_Access_Type_Node implements Type_Node
{
    use Node_Attributes;
    public Type_Node $type;
    public Type_Node $offset;
    public function __construct(Type_Node $type, Type_Node $offset)
    {
        $this->type = $type;
        $this->offset = $offset;
    }
    public function __toString(): string
    {
        if ($this->type instanceof Callable_Type_Node || $this->type instanceof Nullable_Type_Node) {
            return '(' . $this->type . ')[' . $this->offset . ']';
        }
        return $this->type . '[' . $this->offset . ']';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['offset']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}