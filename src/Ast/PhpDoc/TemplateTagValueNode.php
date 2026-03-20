<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use function trim;
class Template_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    /** @var non-empty-string */
    public string $name;
    public ?Type_Node $bound;
    public ?Type_Node $default;
    public ?Type_Node $lower_bound;
    /** @var string (may be empty) */
    public string $description;
    /**
     * @param non-empty-string $name
     */
    public function __construct(string $name, ?Type_Node $bound, string $description, ?Type_Node $default = null, ?Type_Node $lower_bound = null)
    {
        $this->name = $name;
        $this->bound = $bound;
        $this->lower_bound = $lower_bound;
        $this->default = $default;
        $this->description = $description;
    }
    public function __toString(): string
    {
        $upper_bound = $this->bound !== null ? " of {$this->bound}" : '';
        $lower_bound = $this->lower_bound !== null ? " super {$this->lower_bound}" : '';
        $default = $this->default !== null ? " = {$this->default}" : '';
        return trim("{$this->name}{$upper_bound}{$lower_bound}{$default} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['name'], $properties['bound'], $properties['description'], $properties['default'] ?? null, $properties['lowerBound'] ?? null);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}