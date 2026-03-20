<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use function trim;
class Property_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public Type_Node $type;
    public string $property_name;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(Type_Node $type, string $property_name, string $description)
    {
        $this->type = $type;
        $this->property_name = $property_name;
        $this->description = $description;
    }
    public function __toString(): string
    {
        return trim("{$this->type} {$this->property_name} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['propertyName'], $properties['description']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}