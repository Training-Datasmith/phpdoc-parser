<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Printer;

use function array_keys;
use function array_map;
use function assert;
use function count;
use function get_class;
use function get_object_vars;
use function implode;
use function in_array;
use function is_array;
use LogicException;
use Php_Stan\Php_Doc_Parser\Ast\Attribute;
use Php_Stan\Php_Doc_Parser\Ast\Comment;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Array_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Node;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Assert_Tag_Method_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Assert_Tag_Property_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Assert_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Annotation;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Argument;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Array;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Array_Item;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Extends_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Implements_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Method_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Method_Tag_Value_Parameter_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Mixin_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Param_Closure_This_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Param_Immediately_Invoked_Callable_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Param_Later_Invoked_Callable_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Param_Out_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Param_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Child_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Text_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Property_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Pure_Unless_Callable_Is_Impure_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Require_Extends_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Require_Implements_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Return_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Sealed_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Self_Out_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Template_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Throws_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Type_Alias_Import_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Type_Alias_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Uses_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Var_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Array_Shape_Item_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Array_Shape_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Array_Shape_Unsealed_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Array_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Callable_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Callable_Type_Parameter_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Conditional_Type_For_Parameter_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Conditional_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Const_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Generic_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Identifier_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Intersection_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Invalid_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Nullable_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Object_Shape_Item_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Object_Shape_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Offset_Access_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\This_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Union_Type_Node;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use Php_Stan\Php_Doc_Parser\Parser\Token_Iterator;
use function preg_match_all;
use const PREG_SET_ORDER;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function trim;
/**
 * Inspired by https://github.com/nikic/PHP-Parser/tree/36a6dcd04e7b0285e8f0868f44bd4927802f7df1
 *
 * Copyright (c) 2011, Nikita Popov
 * All rights reserved.
 */
final class Printer
{
    /** @var Differ<Node> */
    private Differ $differ;
    /**
     * Map From "{$class}->{$subNode}" to string that should be inserted
     * between elements of this list subnode
     *
     * @var array<string, string>
     */
    private array $list_insertion_map = [Php_Doc_Node::class . '->children' => "\n * ", Union_Type_Node::class . '->types' => '|', Intersection_Type_Node::class . '->types' => '&', Array_Shape_Node::class . '->items' => ', ', Object_Shape_Node::class . '->items' => ', ', Callable_Type_Node::class . '->parameters' => ', ', Callable_Type_Node::class . '->templateTypes' => ', ', Generic_Type_Node::class . '->genericTypes' => ', ', Const_Expr_Array_Node::class . '->items' => ', ', Method_Tag_Value_Node::class . '->parameters' => ', ', Doctrine_Array::class . '->items' => ', ', Doctrine_Annotation::class . '->arguments' => ', '];
    /**
     * [$find, $extraLeft, $extraRight]
     *
     * @var array<string, array{string|null, string, string}>
     */
    private array $empty_list_insertion_map = [Callable_Type_Node::class . '->parameters' => ['(', '', ''], Array_Shape_Node::class . '->items' => ['{', '', ''], Object_Shape_Node::class . '->items' => ['{', '', ''], Doctrine_Array::class . '->items' => ['{', '', ''], Doctrine_Annotation::class . '->arguments' => ['(', '', '']];
    /** @var array<string, list<class-string<TypeNode>>> */
    private array $parentheses_map = [Callable_Type_Node::class . '->returnType' => [Callable_Type_Node::class, Union_Type_Node::class, Intersection_Type_Node::class], Array_Type_Node::class . '->type' => [Callable_Type_Node::class, Union_Type_Node::class, Intersection_Type_Node::class, Const_Type_Node::class, Nullable_Type_Node::class], Offset_Access_Type_Node::class . '->type' => [Callable_Type_Node::class, Union_Type_Node::class, Intersection_Type_Node::class, Nullable_Type_Node::class]];
    /** @var array<string, list<class-string<TypeNode>>> */
    private array $parentheses_list_map = [Intersection_Type_Node::class . '->types' => [Intersection_Type_Node::class, Union_Type_Node::class, Nullable_Type_Node::class], Union_Type_Node::class . '->types' => [Intersection_Type_Node::class, Union_Type_Node::class, Nullable_Type_Node::class]];
    public function print_format_preserving(Php_Doc_Node $node, Php_Doc_Node $original_node, Token_Iterator $original_tokens): string
    {
        $this->differ = new Differ(static function ($a, $b): bool {
            if ($a instanceof Node && $b instanceof Node) {
                return $a === $b->get_attribute(Attribute::ORIGINAL_NODE);
            }
            return false;
        });
        $token_index = 0;
        $result = $this->print_array_format_preserving($node->children, $original_node->children, $original_tokens, $token_index, Php_Doc_Node::class, 'children');
        if ($result !== null) {
            return $result . $original_tokens->get_content_between($token_index, $original_tokens->get_token_count());
        }
        return $this->print($node);
    }
    public function print(Node $node): string
    {
        if ($node instanceof Php_Doc_Node) {
            return "/**\n *" . implode("\n *", array_map(function (Php_Doc_Child_Node $child): string {
                $s = $this->print($child);
                return $s === '' ? '' : ' ' . $s;
            }, $node->children)) . "\n */";
        }
        if ($node instanceof Php_Doc_Text_Node) {
            return $node->text;
        }
        if ($node instanceof Php_Doc_Tag_Node) {
            if ($node->value instanceof Doctrine_Tag_Value_Node) {
                return $this->print($node->value);
            }
            return trim(sprintf('%s %s', $node->name, $this->print($node->value)));
        }
        if ($node instanceof Php_Doc_Tag_Value_Node) {
            return $this->print_tag_value($node);
        }
        if ($node instanceof Type_Node) {
            return $this->print_type($node);
        }
        if ($node instanceof Const_Expr_Node) {
            return $this->print_const_expr($node);
        }
        if ($node instanceof Method_Tag_Value_Parameter_Node) {
            $type = $node->type !== null ? $this->print($node->type) . ' ' : '';
            $is_reference = $node->is_reference ? '&' : '';
            $is_variadic = $node->is_variadic ? '...' : '';
            $default = $node->default_value !== null ? ' = ' . $this->print($node->default_value) : '';
            return "{$type}{$is_reference}{$is_variadic}{$node->parameter_name}{$default}";
        }
        if ($node instanceof Callable_Type_Parameter_Node) {
            $type = $this->print($node->type) . ' ';
            $is_reference = $node->is_reference ? '&' : '';
            $is_variadic = $node->is_variadic ? '...' : '';
            $is_optional = $node->is_optional ? '=' : '';
            return trim("{$type}{$is_reference}{$is_variadic}{$node->parameter_name}") . $is_optional;
        }
        if ($node instanceof Array_Shape_Unsealed_Type_Node) {
            if ($node->key_type !== null) {
                return sprintf('<%s, %s>', $this->print_type($node->key_type), $this->print_type($node->value_type));
            }
            return sprintf('<%s>', $this->print_type($node->value_type));
        }
        if ($node instanceof Doctrine_Annotation) {
            return (string) $node;
        }
        if ($node instanceof Doctrine_Argument) {
            return (string) $node;
        }
        if ($node instanceof Doctrine_Array) {
            return (string) $node;
        }
        if ($node instanceof Doctrine_Array_Item) {
            return (string) $node;
        }
        if ($node instanceof Array_Shape_Item_Node) {
            if ($node->key_name !== null) {
                return sprintf('%s%s: %s', $this->print($node->key_name), $node->optional ? '?' : '', $this->print_type($node->value_type));
            }
            return $this->print_type($node->value_type);
        }
        if ($node instanceof Object_Shape_Item_Node) {
            if ($node->key_name !== null) {
                return sprintf('%s%s: %s', $this->print($node->key_name), $node->optional ? '?' : '', $this->print_type($node->value_type));
            }
            return $this->print_type($node->value_type);
        }
        throw new LogicException(sprintf('Unknown node type %s', get_class($node)));
    }
    private function print_tag_value(Php_Doc_Tag_Value_Node $node): string
    {
        // only nodes that contain another node are handled here
        // the rest falls back on (string) $node
        if ($node instanceof Assert_Tag_Method_Value_Node) {
            $is_negated = $node->is_negated ? '!' : '';
            $is_equality = $node->is_equality ? '=' : '';
            $type = $this->print_type($node->type);
            return trim("{$is_negated}{$is_equality}{$type} {$node->parameter}->{$node->method}() {$node->description}");
        }
        if ($node instanceof Assert_Tag_Property_Value_Node) {
            $is_negated = $node->is_negated ? '!' : '';
            $is_equality = $node->is_equality ? '=' : '';
            $type = $this->print_type($node->type);
            return trim("{$is_negated}{$is_equality}{$type} {$node->parameter}->{$node->property} {$node->description}");
        }
        if ($node instanceof Assert_Tag_Value_Node) {
            $is_negated = $node->is_negated ? '!' : '';
            $is_equality = $node->is_equality ? '=' : '';
            $type = $this->print_type($node->type);
            return trim("{$is_negated}{$is_equality}{$type} {$node->parameter} {$node->description}");
        }
        if ($node instanceof Extends_Tag_Value_Node || $node instanceof Implements_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Method_Tag_Value_Node) {
            $static = $node->is_static ? 'static ' : '';
            $return_type = $node->return_type !== null ? $this->print_type($node->return_type) . ' ' : '';
            $parameters = implode(', ', array_map(fn(Method_Tag_Value_Parameter_Node $parameter): string => $this->print($parameter), $node->parameters));
            $description = $node->description !== '' ? " {$node->description}" : '';
            $template_types = count($node->template_types) > 0 ? '<' . implode(', ', array_map(fn(Template_Tag_Value_Node $template_tag): string => $this->print($template_tag), $node->template_types)) . '>' : '';
            return "{$static}{$return_type}{$node->method_name}{$template_types}({$parameters}){$description}";
        }
        if ($node instanceof Mixin_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Require_Extends_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Require_Implements_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Sealed_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Param_Out_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Param_Tag_Value_Node) {
            $reference = $node->is_reference ? '&' : '';
            $variadic = $node->is_variadic ? '...' : '';
            $type = $this->print_type($node->type);
            return trim("{$type} {$reference}{$variadic}{$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Param_Immediately_Invoked_Callable_Tag_Value_Node) {
            return trim("{$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Param_Later_Invoked_Callable_Tag_Value_Node) {
            return trim("{$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Param_Closure_This_Tag_Value_Node) {
            return trim("{$node->type} {$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Pure_Unless_Callable_Is_Impure_Tag_Value_Node) {
            return trim("{$node->parameter_name} {$node->description}");
        }
        if ($node instanceof Property_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->property_name} {$node->description}");
        }
        if ($node instanceof Return_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Self_Out_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim($type . ' ' . $node->description);
        }
        if ($node instanceof Template_Tag_Value_Node) {
            $upper_bound = $node->bound !== null ? ' of ' . $this->print_type($node->bound) : '';
            $lower_bound = $node->lower_bound !== null ? ' super ' . $this->print_type($node->lower_bound) : '';
            $default = $node->default !== null ? ' = ' . $this->print_type($node->default) : '';
            return trim("{$node->name}{$upper_bound}{$lower_bound}{$default} {$node->description}");
        }
        if ($node instanceof Throws_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Type_Alias_Import_Tag_Value_Node) {
            return trim("{$node->imported_alias} from " . $this->print_type($node->imported_from) . ($node->imported_as !== null ? " as {$node->imported_as}" : ''));
        }
        if ($node instanceof Type_Alias_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$node->alias} {$type}");
        }
        if ($node instanceof Uses_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} {$node->description}");
        }
        if ($node instanceof Var_Tag_Value_Node) {
            $type = $this->print_type($node->type);
            return trim("{$type} " . trim("{$node->variable_name} {$node->description}"));
        }
        return (string) $node;
    }
    private function print_type(Type_Node $node): string
    {
        if ($node instanceof Array_Shape_Node) {
            $items = array_map(fn(Array_Shape_Item_Node $item): string => $this->print($item), $node->items);
            if (!$node->sealed) {
                $items[] = '...' . ($node->unsealed_type === null ? '' : $this->print($node->unsealed_type));
            }
            return $node->kind . '{' . implode(', ', $items) . '}';
        }
        if ($node instanceof Array_Type_Node) {
            return $this->print_offset_access_type($node->type) . '[]';
        }
        if ($node instanceof Callable_Type_Node) {
            if ($node->return_type instanceof Callable_Type_Node || $node->return_type instanceof Union_Type_Node || $node->return_type instanceof Intersection_Type_Node) {
                $return_type = $this->wrap_in_parentheses($node->return_type);
            } else {
                $return_type = $this->print_type($node->return_type);
            }
            $template = $node->template_types !== [] ? '<' . implode(', ', array_map(fn(Template_Tag_Value_Node $template_node): string => $this->print($template_node), $node->template_types)) . '>' : '';
            $parameters = implode(', ', array_map(fn(Callable_Type_Parameter_Node $parameter_node): string => $this->print($parameter_node), $node->parameters));
            return "{$node->identifier}{$template}({$parameters}): {$return_type}";
        }
        if ($node instanceof Conditional_Type_For_Parameter_Node) {
            return sprintf('(%s %s %s ? %s : %s)', $node->parameter_name, $node->negated ? 'is not' : 'is', $this->print_type($node->target_type), $this->print_type($node->if), $this->print_type($node->else));
        }
        if ($node instanceof Conditional_Type_Node) {
            return sprintf('(%s %s %s ? %s : %s)', $this->print_type($node->subject_type), $node->negated ? 'is not' : 'is', $this->print_type($node->target_type), $this->print_type($node->if), $this->print_type($node->else));
        }
        if ($node instanceof Const_Type_Node) {
            return $this->print_const_expr($node->const_expr);
        }
        if ($node instanceof Generic_Type_Node) {
            $generic_types = [];
            foreach ($node->generic_types as $index => $type) {
                $variance = $node->variances[$index] ?? Generic_Type_Node::VARIANCE_INVARIANT;
                if ($variance === Generic_Type_Node::VARIANCE_INVARIANT) {
                    $generic_types[] = $this->print_type($type);
                } elseif ($variance === Generic_Type_Node::VARIANCE_BIVARIANT) {
                    $generic_types[] = '*';
                } else {
                    $generic_types[] = sprintf('%s %s', $variance, $this->print($type));
                }
            }
            return $node->type . '<' . implode(', ', $generic_types) . '>';
        }
        if ($node instanceof Identifier_Type_Node) {
            return $node->name;
        }
        if ($node instanceof Intersection_Type_Node || $node instanceof Union_Type_Node) {
            $items = [];
            foreach ($node->types as $type) {
                if ($type instanceof Intersection_Type_Node || $type instanceof Union_Type_Node || $type instanceof Nullable_Type_Node) {
                    $items[] = $this->wrap_in_parentheses($type);
                    continue;
                }
                $items[] = $this->print_type($type);
            }
            return implode($node instanceof Intersection_Type_Node ? '&' : '|', $items);
        }
        if ($node instanceof Invalid_Type_Node) {
            return (string) $node;
        }
        if ($node instanceof Nullable_Type_Node) {
            if ($node->type instanceof Intersection_Type_Node || $node->type instanceof Union_Type_Node) {
                return '?(' . $this->print_type($node->type) . ')';
            }
            return '?' . $this->print_type($node->type);
        }
        if ($node instanceof Object_Shape_Node) {
            $items = array_map(fn(Object_Shape_Item_Node $item): string => $this->print($item), $node->items);
            return 'object{' . implode(', ', $items) . '}';
        }
        if ($node instanceof Offset_Access_Type_Node) {
            return $this->print_offset_access_type($node->type) . '[' . $this->print_type($node->offset) . ']';
        }
        if ($node instanceof This_Type_Node) {
            return (string) $node;
        }
        throw new LogicException(sprintf('Unknown node type %s', get_class($node)));
    }
    private function wrap_in_parentheses(Type_Node $node): string
    {
        return '(' . $this->print_type($node) . ')';
    }
    private function print_offset_access_type(Type_Node $type): string
    {
        if ($type instanceof Callable_Type_Node || $type instanceof Union_Type_Node || $type instanceof Intersection_Type_Node || $type instanceof Nullable_Type_Node) {
            return $this->wrap_in_parentheses($type);
        }
        return $this->print_type($type);
    }
    private function print_const_expr(Const_Expr_Node $node): string
    {
        // this is fine - ConstExprNode classes do not contain nodes that need smart printer logic
        return (string) $node;
    }
    /**
     * @param Node[] $nodes
     * @param Node[] $originalNodes
     */
    private function print_array_format_preserving(array $nodes, array $original_nodes, Token_Iterator $original_tokens, int &$token_index, string $parent_node_class, string $sub_node_name): ?string
    {
        $diff = $this->differ->diff_with_replacements($original_nodes, $nodes);
        $map_key = $parent_node_class . '->' . $sub_node_name;
        $insert_str = $this->list_insertion_map[$map_key] ?? null;
        $result = '';
        $before_first_keep_or_replace = true;
        $delayed_add = [];
        $insert_newline = false;
        [$is_multiline, $before_asterisk_indent, $after_asterisk_indent] = $this->is_multiline($token_index, $original_nodes, $original_tokens);
        if ($insert_str === "\n * ") {
            $insert_str = sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
        }
        foreach ($diff as $i => $diff_elem) {
            $diff_type = $diff_elem->type;
            $arr_item = $diff_elem->new;
            $orig_array_item = $diff_elem->old;
            if ($diff_type === Diff_Elem::TYPE_KEEP || $diff_type === Diff_Elem::TYPE_REPLACE) {
                $before_first_keep_or_replace = false;
                if (!$arr_item instanceof Node || !$orig_array_item instanceof Node) {
                    return null;
                }
                /** @var int $itemStartPos */
                $item_start_pos = $orig_array_item->get_attribute(Attribute::START_INDEX);
                /** @var int $itemEndPos */
                $item_end_pos = $orig_array_item->get_attribute(Attribute::END_INDEX);
                if ($item_start_pos < 0 || $item_end_pos < 0 || $item_start_pos < $token_index) {
                    throw new LogicException();
                }
                $comments = $arr_item->get_attribute(Attribute::COMMENTS) ?? [];
                $orig_comments = $orig_array_item->get_attribute(Attribute::COMMENTS) ?? [];
                $comment_start_pos = count($orig_comments) > 0 ? $orig_comments[0]->start_index : $item_start_pos;
                assert($comment_start_pos >= 0);
                $result .= $original_tokens->get_content_between($token_index, $item_start_pos);
                if (count($delayed_add) > 0) {
                    foreach ($delayed_add as $delayed_add_node) {
                        $parentheses_needed = isset($this->parentheses_list_map[$map_key]) && in_array(get_class($delayed_add_node), $this->parentheses_list_map[$map_key], true);
                        if ($parentheses_needed) {
                            $result .= '(';
                        }
                        if ($insert_newline) {
                            $delayed_add_comments = $delayed_add_node->get_attribute(Attribute::COMMENTS) ?? [];
                            if (count($delayed_add_comments) > 0) {
                                $result .= $this->print_comments($delayed_add_comments, $before_asterisk_indent, $after_asterisk_indent);
                                $result .= sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                            }
                        }
                        $result .= $this->print_node_format_preserving($delayed_add_node, $original_tokens);
                        if ($parentheses_needed) {
                            $result .= ')';
                        }
                        if ($insert_newline) {
                            $result .= $insert_str . sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                        } else {
                            $result .= $insert_str;
                        }
                    }
                    $delayed_add = [];
                }
                $parentheses_needed = isset($this->parentheses_list_map[$map_key]) && in_array(get_class($arr_item), $this->parentheses_list_map[$map_key], true) && !in_array(get_class($orig_array_item), $this->parentheses_list_map[$map_key], true);
                $add_parentheses = $parentheses_needed && !$original_tokens->has_parentheses($item_start_pos, $item_end_pos);
                if ($add_parentheses) {
                    $result .= '(';
                }
                if ($comments !== $orig_comments) {
                    if (count($comments) > 0) {
                        $result .= $this->print_comments($comments, $before_asterisk_indent, $after_asterisk_indent);
                        $result .= sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                    }
                }
                $result .= $this->print_node_format_preserving($arr_item, $original_tokens);
                if ($add_parentheses) {
                    $result .= ')';
                }
                $token_index = $item_end_pos + 1;
            } elseif ($diff_type === Diff_Elem::TYPE_ADD) {
                if ($insert_str === null) {
                    return null;
                }
                if (!$arr_item instanceof Node) {
                    return null;
                }
                if ($insert_str === ', ' && $is_multiline || count($arr_item->get_attribute(Attribute::COMMENTS) ?? []) > 0) {
                    $insert_str = ',';
                    $insert_newline = true;
                }
                if ($before_first_keep_or_replace) {
                    // Will be inserted at the next "replace" or "keep" element
                    $delayed_add[] = $arr_item;
                    continue;
                }
                /** @var int $itemEndPos */
                $item_end_pos = $token_index - 1;
                if ($insert_newline) {
                    $comments = $arr_item->get_attribute(Attribute::COMMENTS) ?? [];
                    $result .= $insert_str;
                    if (count($comments) > 0) {
                        $result .= sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                        $result .= $this->print_comments($comments, $before_asterisk_indent, $after_asterisk_indent);
                    }
                    $result .= sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                } else {
                    $result .= $insert_str;
                }
                $parentheses_needed = isset($this->parentheses_list_map[$map_key]) && in_array(get_class($arr_item), $this->parentheses_list_map[$map_key], true);
                if ($parentheses_needed) {
                    $result .= '(';
                }
                $result .= $this->print_node_format_preserving($arr_item, $original_tokens);
                if ($parentheses_needed) {
                    $result .= ')';
                }
                $token_index = $item_end_pos + 1;
            } elseif ($diff_type === Diff_Elem::TYPE_REMOVE) {
                if (!$orig_array_item instanceof Node) {
                    return null;
                }
                /** @var int $itemStartPos */
                $item_start_pos = $orig_array_item->get_attribute(Attribute::START_INDEX);
                /** @var int $itemEndPos */
                $item_end_pos = $orig_array_item->get_attribute(Attribute::END_INDEX);
                if ($item_start_pos < 0 || $item_end_pos < 0) {
                    throw new LogicException();
                }
                if ($i === 0) {
                    // If we're removing from the start, keep the tokens before the node and drop those after it,
                    // instead of the other way around.
                    $original_tokens_array = $original_tokens->get_tokens();
                    for ($j = $token_index; $j < $item_start_pos; $j++) {
                        if ($original_tokens_array[$j][Lexer::TYPE_OFFSET] === Lexer::TOKEN_PHPDOC_EOL) {
                            break;
                        }
                        $result .= $original_tokens_array[$j][Lexer::VALUE_OFFSET];
                    }
                }
                $token_index = $item_end_pos + 1;
            }
        }
        if (count($delayed_add) > 0) {
            if (!isset($this->empty_list_insertion_map[$map_key])) {
                return null;
            }
            [$find_token, $extra_left, $extra_right] = $this->empty_list_insertion_map[$map_key];
            if ($find_token !== null) {
                $original_tokens_array = $original_tokens->get_tokens();
                for (; $token_index < count($original_tokens_array); $token_index++) {
                    $result .= $original_tokens_array[$token_index][Lexer::VALUE_OFFSET];
                    if ($original_tokens_array[$token_index][Lexer::VALUE_OFFSET] !== $find_token) {
                        continue;
                    }
                    $token_index++;
                    break;
                }
            }
            $first = true;
            $result .= $extra_left;
            foreach ($delayed_add as $delayed_add_node) {
                if (!$first) {
                    $result .= $insert_str;
                    if ($insert_newline) {
                        $result .= sprintf('%s%s*%s', $original_tokens->get_detected_newline() ?? "\n", $before_asterisk_indent, $after_asterisk_indent);
                    }
                }
                $result .= $this->print_node_format_preserving($delayed_add_node, $original_tokens);
                $first = false;
            }
            $result .= $extra_right;
        }
        return $result;
    }
    /**
     * @param list<Comment> $comments
     */
    private function print_comments(array $comments, string $before_asterisk_indent, string $after_asterisk_indent): string
    {
        $formatted_comments = [];
        foreach ($comments as $comment) {
            $formatted_comments[] = str_replace("\n", "\n" . $before_asterisk_indent . '*' . $after_asterisk_indent, $comment->get_reformatted_text());
        }
        return implode("\n{$before_asterisk_indent}*{$after_asterisk_indent}", $formatted_comments);
    }
    /**
     * @param array<Node|null> $nodes
     * @return array{bool, string, string}
     */
    private function is_multiline(int $initial_index, array $nodes, Token_Iterator $original_tokens): array
    {
        $is_multiline = count($nodes) > 1;
        $pos = $initial_index;
        $all_text = '';
        /** @var Node|null $node */
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }
            $end_pos = $node->get_attribute(Attribute::END_INDEX) + 1;
            $text = $original_tokens->get_content_between($pos, $end_pos);
            $all_text .= $text;
            if (strpos($text, "\n") === false) {
                // We require that a newline is present between *every* item. If the formatting
                // is inconsistent, with only some items having newlines, we don't consider it
                // as multiline
                $is_multiline = false;
            }
            $pos = $end_pos;
        }
        $c = preg_match_all('~\n(?<before>[\x09\x20]*)\*(?<after>\x20*)~', $all_text, $matches, PREG_SET_ORDER);
        if ($c === 0) {
            return [$is_multiline, ' ', '  '];
        }
        $before = '';
        $after = '';
        foreach ($matches as $match) {
            if (strlen($match['before']) > strlen($before)) {
                $before = $match['before'];
            }
            if (strlen($match['after']) <= strlen($after)) {
                continue;
            }
            $after = $match['after'];
        }
        $before = strlen($before) === 0 ? ' ' : $before;
        $after = strlen($after) === 0 ? '  ' : $after;
        return [$is_multiline, $before, $after];
    }
    private function print_node_format_preserving(Node $node, Token_Iterator $original_tokens): string
    {
        /** @var Node|null $originalNode */
        $original_node = $node->get_attribute(Attribute::ORIGINAL_NODE);
        if ($original_node === null) {
            return $this->print($node);
        }
        $class = get_class($node);
        if ($class !== get_class($original_node)) {
            throw new LogicException();
        }
        $start_pos = $original_node->get_attribute(Attribute::START_INDEX);
        $end_pos = $original_node->get_attribute(Attribute::END_INDEX);
        if ($start_pos < 0 || $end_pos < 0) {
            throw new LogicException();
        }
        $result = '';
        $pos = $start_pos;
        $sub_node_names = array_keys(get_object_vars($node));
        foreach ($sub_node_names as $sub_node_name) {
            $sub_node = $node->{$sub_node_name};
            $orig_sub_node = $original_node->{$sub_node_name};
            if (!$sub_node instanceof Node && $sub_node !== null || !$orig_sub_node instanceof Node && $orig_sub_node !== null) {
                if ($sub_node === $orig_sub_node) {
                    // Unchanged, can reuse old code
                    continue;
                }
                if (is_array($sub_node) && is_array($orig_sub_node)) {
                    // Array subnode changed, we might be able to reconstruct it
                    $list_result = $this->print_array_format_preserving($sub_node, $orig_sub_node, $original_tokens, $pos, $class, $sub_node_name);
                    if ($list_result === null) {
                        return $this->print($node);
                    }
                    $result .= $list_result;
                    continue;
                }
                return $this->print($node);
            }
            if ($orig_sub_node === null) {
                if ($sub_node === null) {
                    // Both null, nothing to do
                    continue;
                }
                return $this->print($node);
            }
            $sub_start_pos = $orig_sub_node->get_attribute(Attribute::START_INDEX);
            $sub_end_pos = $orig_sub_node->get_attribute(Attribute::END_INDEX);
            if ($sub_start_pos < 0 || $sub_end_pos < 0) {
                throw new LogicException();
            }
            if ($sub_end_pos < $sub_start_pos) {
                return $this->print($node);
            }
            if ($sub_node === null) {
                return $this->print($node);
            }
            $result .= $original_tokens->get_content_between($pos, $sub_start_pos);
            $map_key = get_class($node) . '->' . $sub_node_name;
            $parentheses_needed = isset($this->parentheses_map[$map_key]) && in_array(get_class($sub_node), $this->parentheses_map[$map_key], true);
            if ($sub_node->get_attribute(Attribute::ORIGINAL_NODE) !== null) {
                $parentheses_needed = $parentheses_needed && !in_array(get_class($sub_node->get_attribute(Attribute::ORIGINAL_NODE)), $this->parentheses_map[$map_key], true);
            }
            $add_parentheses = $parentheses_needed && !$original_tokens->has_parentheses($sub_start_pos, $sub_end_pos);
            if ($add_parentheses) {
                $result .= '(';
            }
            $result .= $this->print_node_format_preserving($sub_node, $original_tokens);
            if ($add_parentheses) {
                $result .= ')';
            }
            $pos = $sub_end_pos + 1;
        }
        return $result . $original_tokens->get_content_between($pos, $end_pos + 1);
    }
}