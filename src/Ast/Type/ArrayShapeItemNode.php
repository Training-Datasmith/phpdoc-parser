<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Integer_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_String_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Fetch_Node;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Array_Shape_Item_Node implements Node
{
    use Node_Attributes;
    /** @var ConstExprIntegerNode|ConstExprStringNode|ConstFetchNode|IdentifierTypeNode|null */
    public $key_name;
    public bool $optional;
    public Type_Node $value_type;
    /**
     * @param ConstExprIntegerNode|ConstExprStringNode|ConstFetchNode|IdentifierTypeNode|null $keyName
     */
    public function __construct($key_name, bool $optional, Type_Node $value_type)
    {
        $this->key_name = $key_name;
        $this->optional = $optional;
        $this->value_type = $value_type;
    }
    public function __toString(): string
    {
        if ($this->key_name !== null) {
            return sprintf('%s%s: %s', (string) $this->key_name, $this->optional ? '?' : '', (string) $this->value_type);
        }
        return (string) $this->value_type;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['keyName'], $properties['optional'], $properties['valueType']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}