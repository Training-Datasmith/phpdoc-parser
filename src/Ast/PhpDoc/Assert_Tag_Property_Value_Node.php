<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use function trim;
class Assert_Tag_Property_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public Type_Node $type;
    public string $parameter;
    public string $property;
    public bool $is_negated;
    public bool $is_equality;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(Type_Node $type, string $parameter, string $property, bool $is_negated, string $description, bool $is_equality)
    {
        $this->type = $type;
        $this->parameter = $parameter;
        $this->property = $property;
        $this->is_negated = $is_negated;
        $this->is_equality = $is_equality;
        $this->description = $description;
    }
    public function __toString(): string
    {
        $is_negated = $this->is_negated ? '!' : '';
        $is_equality = $this->is_equality ? '=' : '';
        return trim("{$is_negated}{$is_equality}{$this->type} {$this->parameter}->{$this->property} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['parameter'], $properties['property'], $properties['isNegated'], $properties['description'], $properties['isEquality']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}