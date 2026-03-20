<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Node;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
class Method_Tag_Value_Parameter_Node implements Node
{
    use Node_Attributes;
    public ?Type_Node $type = null;
    public bool $is_reference;
    public bool $is_variadic;
    public string $parameter_name;
    public ?Const_Expr_Node $default_value = null;
    public function __construct(?Type_Node $type, bool $is_reference, bool $is_variadic, string $parameter_name, ?Const_Expr_Node $default_value)
    {
        $this->type = $type;
        $this->is_reference = $is_reference;
        $this->is_variadic = $is_variadic;
        $this->parameter_name = $parameter_name;
        $this->default_value = $default_value;
    }
    public function __toString(): string
    {
        $type = $this->type !== null ? "{$this->type} " : '';
        $is_reference = $this->is_reference ? '&' : '';
        $is_variadic = $this->is_variadic ? '...' : '';
        $default = $this->default_value !== null ? " = {$this->default_value}" : '';
        return "{$type}{$is_reference}{$is_variadic}{$this->parameter_name}{$default}";
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['isReference'], $properties['isVariadic'], $properties['parameterName'], $properties['defaultValue']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}