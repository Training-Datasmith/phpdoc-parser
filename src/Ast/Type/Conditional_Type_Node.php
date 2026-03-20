<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Conditional_Type_Node implements Type_Node
{
    use Node_Attributes;
    public Type_Node $subject_type;
    public Type_Node $target_type;
    public Type_Node $if;
    public Type_Node $else;
    public bool $negated;
    public function __construct(Type_Node $subject_type, Type_Node $target_type, Type_Node $if, Type_Node $else, bool $negated)
    {
        $this->subject_type = $subject_type;
        $this->target_type = $target_type;
        $this->if = $if;
        $this->else = $else;
        $this->negated = $negated;
    }
    public function __toString(): string
    {
        return sprintf('(%s %s %s ? %s : %s)', $this->subject_type, $this->negated ? 'is not' : 'is', $this->target_type, $this->if, $this->else);
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['subjectType'], $properties['targetType'], $properties['if'], $properties['else'], $properties['negated']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}