<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast;

/**
 * Inspired by https://github.com/nikic/PHP-Parser/tree/36a6dcd04e7b0285e8f0868f44bd4927802f7df1
 *
 * Copyright (c) 2011, Nikita Popov
 * All rights reserved.
 */
abstract class Abstract_Node_Visitor implements Node_Visitor
{
    public function before_traverse(array $nodes): ?array
    {
        return null;
    }
    public function enter_node(Node $node)
    {
        return null;
    }
    public function leave_node(Node $node)
    {
        return null;
    }
    public function after_traverse(array $nodes): ?array
    {
        return null;
    }
}