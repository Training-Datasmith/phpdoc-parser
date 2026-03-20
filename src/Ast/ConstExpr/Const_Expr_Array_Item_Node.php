<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Const_Expr;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Const_Expr_Array_Item_Node implements Const_Expr_Node
{
    use Node_Attributes;
    public ?Const_Expr_Node $key = null;
    public Const_Expr_Node $value;
    public function __construct(?Const_Expr_Node $key, Const_Expr_Node $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
    public function __toString(): string
    {
        if ($this->key !== null) {
            return sprintf('%s => %s', $this->key, $this->value);
        }
        return (string) $this->value;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['key'], $properties['value']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}