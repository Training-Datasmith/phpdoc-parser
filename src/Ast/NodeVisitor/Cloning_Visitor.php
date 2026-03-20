<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Node_Visitor;

use Php_Stan\Php_Doc_Parser\Ast\Abstract_Node_Visitor;
use Php_Stan\Php_Doc_Parser\Ast\Attribute;
use Php_Stan\Php_Doc_Parser\Ast\Node;
final class Cloning_Visitor extends Abstract_Node_Visitor
{
    public function enter_node(Node $original_node): Node
    {
        $node = clone $original_node;
        $node->set_attribute(Attribute::ORIGINAL_NODE, $original_node);
        return $node;
    }
}