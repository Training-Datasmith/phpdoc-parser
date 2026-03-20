<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Array_Shape_Unsealed_Type_Node implements Node
{
    use Node_Attributes;
    public Type_Node $value_type;
    public ?Type_Node $key_type = null;
    public function __construct(Type_Node $value_type, ?Type_Node $key_type)
    {
        $this->value_type = $value_type;
        $this->key_type = $key_type;
    }
    public function __toString(): string
    {
        if ($this->key_type !== null) {
            return sprintf('<%s, %s>', $this->key_type, $this->value_type);
        }
        return sprintf('<%s>', $this->value_type);
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['valueType'], $properties['keyType']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}