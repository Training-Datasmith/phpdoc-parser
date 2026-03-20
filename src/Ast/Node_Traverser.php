<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast;

use function array_keys;
use function array_pop;
use function array_splice;
use function count;
use function get_class;
use function get_object_vars;
use function gettype;
use function is_array;
use LogicException;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Child_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use function sprintf;
/**
 * Inspired by https://github.com/nikic/PHP-Parser/tree/36a6dcd04e7b0285e8f0868f44bd4927802f7df1
 *
 * Copyright (c) 2011, Nikita Popov
 * All rights reserved.
 */
final class Node_Traverser
{
    /**
     * If NodeVisitor::enterNode() returns DONT_TRAVERSE_CHILDREN, child nodes
     * of the current node will not be traversed for any visitors.
     *
     * For subsequent visitors enterNode() will still be called on the current
     * node and leaveNode() will also be invoked for the current node.
     */
    public const DONT_TRAVERSE_CHILDREN = 1;
    /**
     * If NodeVisitor::enterNode() or NodeVisitor::leaveNode() returns
     * STOP_TRAVERSAL, traversal is aborted.
     *
     * The afterTraverse() method will still be invoked.
     */
    public const STOP_TRAVERSAL = 2;
    /**
     * If NodeVisitor::leaveNode() returns REMOVE_NODE for a node that occurs
     * in an array, it will be removed from the array.
     *
     * For subsequent visitors leaveNode() will still be invoked for the
     * removed node.
     */
    public const REMOVE_NODE = 3;
    /**
     * If NodeVisitor::enterNode() returns DONT_TRAVERSE_CURRENT_AND_CHILDREN, child nodes
     * of the current node will not be traversed for any visitors.
     *
     * For subsequent visitors enterNode() will not be called as well.
     * leaveNode() will be invoked for visitors that has enterNode() method invoked.
     */
    public const DONT_TRAVERSE_CURRENT_AND_CHILDREN = 4;
    /** @var list<NodeVisitor> Visitors */
    private array $visitors = [];
    /** @var bool Whether traversal should be stopped */
    private bool $stop_traversal;
    /**
     * @param list<NodeVisitor> $visitors
     */
    public function __construct(array $visitors)
    {
        $this->visitors = $visitors;
    }
    /**
     * Traverses an array of nodes using the registered visitors.
     *
     * @param Node[] $nodes Array of nodes
     *
     * @return Node[] Traversed array of nodes
     */
    public function traverse(array $nodes): array
    {
        $this->stop_traversal = false;
        foreach ($this->visitors as $visitor) {
            $return = $visitor->before_traverse($nodes);
            if ($return === null) {
                continue;
            }
            $nodes = $return;
        }
        $nodes = $this->traverse_array($nodes);
        foreach ($this->visitors as $visitor) {
            $return = $visitor->after_traverse($nodes);
            if ($return === null) {
                continue;
            }
            $nodes = $return;
        }
        return $nodes;
    }
    /**
     * Recursively traverse a node.
     *
     * @param Node $node Node to traverse.
     *
     * @return Node Result of traversal (may be original node or new one)
     */
    private function traverse_node(Node $node): Node
    {
        $sub_node_names = array_keys(get_object_vars($node));
        foreach ($sub_node_names as $name) {
            $sub_node =& $node->{$name};
            if (is_array($sub_node)) {
                $sub_node = $this->traverse_array($sub_node);
                if ($this->stop_traversal) {
                    break;
                }
            } elseif ($sub_node instanceof Node) {
                $traverse_children = true;
                $break_visitor_index = null;
                foreach ($this->visitors as $visitor_index => $visitor) {
                    $return = $visitor->enter_node($sub_node);
                    if ($return === null) {
                        continue;
                    }
                    if ($return instanceof Node) {
                        $this->ensure_replacement_reasonable($sub_node, $return);
                        $sub_node = $return;
                    } elseif ($return === self::DONT_TRAVERSE_CHILDREN) {
                        $traverse_children = false;
                    } elseif ($return === self::DONT_TRAVERSE_CURRENT_AND_CHILDREN) {
                        $traverse_children = false;
                        $break_visitor_index = $visitor_index;
                        break;
                    } elseif ($return === self::STOP_TRAVERSAL) {
                        $this->stop_traversal = true;
                        break 2;
                    } else {
                        throw new LogicException('enterNode() returned invalid value of type ' . gettype($return));
                    }
                }
                if ($traverse_children) {
                    $sub_node = $this->traverse_node($sub_node);
                    if ($this->stop_traversal) {
                        break;
                    }
                }
                foreach ($this->visitors as $visitor_index => $visitor) {
                    $return = $visitor->leave_node($sub_node);
                    if ($return !== null) {
                        if ($return instanceof Node) {
                            $this->ensure_replacement_reasonable($sub_node, $return);
                            $sub_node = $return;
                        } elseif ($return === self::STOP_TRAVERSAL) {
                            $this->stop_traversal = true;
                            break 2;
                        } elseif (is_array($return)) {
                            throw new LogicException('leaveNode() may only return an array ' . 'if the parent structure is an array');
                        } else {
                            throw new LogicException('leaveNode() returned invalid value of type ' . gettype($return));
                        }
                    }
                    if ($break_visitor_index === $visitor_index) {
                        break;
                    }
                }
            }
        }
        return $node;
    }
    /**
     * Recursively traverse array (usually of nodes).
     *
     * @param mixed[] $nodes Array to traverse
     *
     * @return mixed[] Result of traversal (may be original array or changed one)
     */
    private function traverse_array(array $nodes): array
    {
        $do_nodes = [];
        foreach ($nodes as $i => &$node) {
            if ($node instanceof Node) {
                $traverse_children = true;
                $break_visitor_index = null;
                foreach ($this->visitors as $visitor_index => $visitor) {
                    $return = $visitor->enter_node($node);
                    if ($return === null) {
                        continue;
                    }
                    if ($return instanceof Node) {
                        $this->ensure_replacement_reasonable($node, $return);
                        $node = $return;
                    } elseif (is_array($return)) {
                        $do_nodes[] = [$i, $return];
                        continue 2;
                    } elseif ($return === self::REMOVE_NODE) {
                        $do_nodes[] = [$i, []];
                        continue 2;
                    } elseif ($return === self::DONT_TRAVERSE_CHILDREN) {
                        $traverse_children = false;
                    } elseif ($return === self::DONT_TRAVERSE_CURRENT_AND_CHILDREN) {
                        $traverse_children = false;
                        $break_visitor_index = $visitor_index;
                        break;
                    } elseif ($return === self::STOP_TRAVERSAL) {
                        $this->stop_traversal = true;
                        break 2;
                    } else {
                        throw new LogicException('enterNode() returned invalid value of type ' . gettype($return));
                    }
                }
                if ($traverse_children) {
                    $node = $this->traverse_node($node);
                    if ($this->stop_traversal) {
                        break;
                    }
                }
                foreach ($this->visitors as $visitor_index => $visitor) {
                    $return = $visitor->leave_node($node);
                    if ($return !== null) {
                        if ($return instanceof Node) {
                            $this->ensure_replacement_reasonable($node, $return);
                            $node = $return;
                        } elseif (is_array($return)) {
                            $do_nodes[] = [$i, $return];
                            break;
                        } elseif ($return === self::REMOVE_NODE) {
                            $do_nodes[] = [$i, []];
                            break;
                        } elseif ($return === self::STOP_TRAVERSAL) {
                            $this->stop_traversal = true;
                            break 2;
                        } else {
                            throw new LogicException('leaveNode() returned invalid value of type ' . gettype($return));
                        }
                    }
                    if ($break_visitor_index === $visitor_index) {
                        break;
                    }
                }
            } elseif (is_array($node)) {
                throw new LogicException('Invalid node structure: Contains nested arrays');
            }
        }
        if (count($do_nodes) > 0) {
            while ([$i, $replace] = array_pop($do_nodes)) {
                array_splice($nodes, $i, 1, $replace);
            }
        }
        return $nodes;
    }
    private function ensure_replacement_reasonable(Node $old, Node $new): void
    {
        if ($old instanceof Type_Node && !$new instanceof Type_Node) {
            throw new LogicException(sprintf('Trying to replace TypeNode with %s', get_class($new)));
        }
        if ($old instanceof Const_Expr_Node && !$new instanceof Const_Expr_Node) {
            throw new LogicException(sprintf('Trying to replace ConstExprNode with %s', get_class($new)));
        }
        if ($old instanceof Php_Doc_Child_Node && !$new instanceof Php_Doc_Child_Node) {
            throw new LogicException(sprintf('Trying to replace PhpDocChildNode with %s', get_class($new)));
        }
        if ($old instanceof Php_Doc_Tag_Value_Node && !$new instanceof Php_Doc_Tag_Value_Node) {
            throw new LogicException(sprintf('Trying to replace PhpDocTagValueNode with %s', get_class($new)));
        }
    }
}