<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Array_Shape_Node implements Type_Node
{
    use Node_Attributes;
    public const KIND_ARRAY = 'array';
    public const KIND_LIST = 'list';
    public const KIND_NON_EMPTY_ARRAY = 'non-empty-array';
    public const KIND_NON_EMPTY_LIST = 'non-empty-list';
    /** @var ArrayShapeItemNode[] */
    public array $items;
    public bool $sealed;
    /** @var self::KIND_* */
    public $kind;
    public ?Array_Shape_Unsealed_Type_Node $unsealed_type = null;
    /**
     * @param ArrayShapeItemNode[] $items
     * @param self::KIND_* $kind
     */
    private function __construct(array $items, bool $sealed = true, ?Array_Shape_Unsealed_Type_Node $unsealed_type = null, string $kind = self::KIND_ARRAY)
    {
        $this->items = $items;
        $this->sealed = $sealed;
        $this->unsealed_type = $unsealed_type;
        $this->kind = $kind;
    }
    /**
     * @param ArrayShapeItemNode[] $items
     * @param self::KIND_* $kind
     */
    public static function create_sealed(array $items, string $kind = self::KIND_ARRAY): self
    {
        return new self($items, true, null, $kind);
    }
    /**
     * @param ArrayShapeItemNode[] $items
     * @param self::KIND_* $kind
     */
    public static function create_unsealed(array $items, ?Array_Shape_Unsealed_Type_Node $unsealed_type, string $kind = self::KIND_ARRAY): self
    {
        return new self($items, false, $unsealed_type, $kind);
    }
    public function __toString(): string
    {
        $items = $this->items;
        if (!$this->sealed) {
            $items[] = '...' . $this->unsealed_type;
        }
        return $this->kind . '{' . implode(', ', $items) . '}';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['items'], $properties['sealed'], $properties['unsealedType'], $properties['kind']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}