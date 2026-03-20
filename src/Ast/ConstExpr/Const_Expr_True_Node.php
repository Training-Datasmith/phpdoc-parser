<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Const_Expr;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Const_Expr_True_Node implements Const_Expr_Node
{
    use Node_Attributes;
    public function __toString(): string
    {
        return 'true';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self();
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}