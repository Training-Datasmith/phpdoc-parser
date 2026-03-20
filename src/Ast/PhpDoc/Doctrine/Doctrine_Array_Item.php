<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine;

use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Integer_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_String_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Fetch_Node;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Identifier_Type_Node;
/**
 * @phpstan-import-type ValueType from DoctrineArgument
 * @phpstan-type KeyType = ConstExprIntegerNode|ConstExprStringNode|IdentifierTypeNode|ConstFetchNode|null
 */
class Doctrine_Array_Item implements Node
{
    use Node_Attributes;
    /** @var KeyType */
    public $key;
    /** @var ValueType */
    public $value;
    /**
     * @param KeyType $key
     * @param ValueType $value
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
    public function __toString(): string
    {
        if ($this->key === null) {
            return (string) $this->value;
        }
        return $this->key . '=' . $this->value;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['key'], $properties['value']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}