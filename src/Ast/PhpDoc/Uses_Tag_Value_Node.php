<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Generic_Type_Node;
use function trim;
class Uses_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public Generic_Type_Node $type;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(Generic_Type_Node $type, string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }
    public function __toString(): string
    {
        return trim("{$this->type} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['description']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}