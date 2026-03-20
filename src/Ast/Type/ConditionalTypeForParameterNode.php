<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Conditional_Type_For_Parameter_Node implements Type_Node
{
    use Node_Attributes;
    public string $parameter_name;
    public Type_Node $target_type;
    public Type_Node $if;
    public Type_Node $else;
    public bool $negated;
    public function __construct(string $parameter_name, Type_Node $target_type, Type_Node $if, Type_Node $else, bool $negated)
    {
        $this->parameter_name = $parameter_name;
        $this->target_type = $target_type;
        $this->if = $if;
        $this->else = $else;
        $this->negated = $negated;
    }
    public function __toString(): string
    {
        return sprintf('(%s %s %s ? %s : %s)', $this->parameter_name, $this->negated ? 'is not' : 'is', $this->target_type, $this->if, $this->else);
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['parameterName'], $properties['targetType'], $properties['if'], $properties['else'], $properties['negated']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}