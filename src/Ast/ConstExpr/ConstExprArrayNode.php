<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Const_Expr;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Const_Expr_Array_Node implements Const_Expr_Node
{
    use Node_Attributes;
    /** @var ConstExprArrayItemNode[] */
    public array $items;
    /**
     * @param ConstExprArrayItemNode[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
    public function __toString(): string
    {
        return '[' . implode(', ', $this->items) . ']';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['items']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}