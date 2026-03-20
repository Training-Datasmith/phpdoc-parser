<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Object_Shape_Node implements Type_Node
{
    use Node_Attributes;
    /** @var ObjectShapeItemNode[] */
    public array $items;
    /**
     * @param ObjectShapeItemNode[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
    public function __toString(): string
    {
        $items = $this->items;
        return 'object{' . implode(', ', $items) . '}';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['items']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}