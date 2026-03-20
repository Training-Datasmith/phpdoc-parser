<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function trim;
class Typeless_Param_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public bool $is_reference;
    public bool $is_variadic;
    public string $parameter_name;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(bool $is_variadic, string $parameter_name, string $description, bool $is_reference)
    {
        $this->is_reference = $is_reference;
        $this->is_variadic = $is_variadic;
        $this->parameter_name = $parameter_name;
        $this->description = $description;
    }
    public function __toString(): string
    {
        $reference = $this->is_reference ? '&' : '';
        $variadic = $this->is_variadic ? '...' : '';
        return trim("{$reference}{$variadic}{$this->parameter_name} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['isVariadic'], $properties['parameterName'], $properties['description'], $properties['isReference']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}