<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function trim;
class Callable_Type_Parameter_Node implements Node
{
    use Node_Attributes;
    public Type_Node $type;
    public bool $is_reference;
    public bool $is_variadic;
    /** @var string (may be empty) */
    public string $parameter_name;
    public bool $is_optional;
    public function __construct(Type_Node $type, bool $is_reference, bool $is_variadic, string $parameter_name, bool $is_optional)
    {
        $this->type = $type;
        $this->is_reference = $is_reference;
        $this->is_variadic = $is_variadic;
        $this->parameter_name = $parameter_name;
        $this->is_optional = $is_optional;
    }
    public function __toString(): string
    {
        $type = "{$this->type} ";
        $is_reference = $this->is_reference ? '&' : '';
        $is_variadic = $this->is_variadic ? '...' : '';
        $is_optional = $this->is_optional ? '=' : '';
        return trim("{$type}{$is_reference}{$is_variadic}{$this->parameter_name}") . $is_optional;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['isReference'], $properties['isVariadic'], $properties['parameterName'], $properties['isOptional']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}