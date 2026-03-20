<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use function array_map;
use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Union_Type_Node implements Type_Node
{
    use Node_Attributes;
    /** @var TypeNode[] */
    public array $types;
    /**
     * @param TypeNode[] $types
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }
    public function __toString(): string
    {
        return '(' . implode(' | ', array_map(static function (Type_Node $type): string {
            if ($type instanceof Nullable_Type_Node) {
                return '(' . $type . ')';
            }
            return (string) $type;
        }, $this->types)) . ')';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['types']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}