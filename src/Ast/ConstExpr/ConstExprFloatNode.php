<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Const_Expr;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Const_Expr_Float_Node implements Const_Expr_Node
{
    use Node_Attributes;
    public string $value;
    public function __construct(string $value)
    {
        $this->value = $value;
    }
    public function __toString(): string
    {
        return $this->value;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['value']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}