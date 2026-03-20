<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Const_Type_Node implements Type_Node
{
    use Node_Attributes;
    public Const_Expr_Node $const_expr;
    public function __construct(Const_Expr_Node $const_expr)
    {
        $this->const_expr = $const_expr;
    }
    public function __toString(): string
    {
        return $this->const_expr->__toString();
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['constExpr']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}