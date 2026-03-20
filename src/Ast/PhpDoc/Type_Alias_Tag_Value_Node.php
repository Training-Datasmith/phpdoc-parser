<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use function trim;
class Type_Alias_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public string $alias;
    public Type_Node $type;
    public function __construct(string $alias, Type_Node $type)
    {
        $this->alias = $alias;
        $this->type = $type;
    }
    public function __toString(): string
    {
        return trim("{$this->alias} {$this->type}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['alias'], $properties['type']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}