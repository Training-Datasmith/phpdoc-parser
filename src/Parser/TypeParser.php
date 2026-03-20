<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Parser;

use function in_array;
use LogicException;
use Php_Stan\Php_Doc_Parser\Ast;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Template_Tag_Value_Node;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use Php_Stan\Php_Doc_Parser\Parser_Config;
use function str_replace;
use function strlen;
use function strpos;
use function substr_compare;
class Type_Parser
{
    private Parser_Config $config;
    private Const_Expr_Parser $const_expr_parser;
    public function __construct(Parser_Config $config, Const_Expr_Parser $const_expr_parser)
    {
        $this->config = $config;
        $this->const_expr_parser = $const_expr_parser;
    }
    /** @phpstan-impure */
    public function parse(Token_Iterator $tokens): Ast\Type\Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_NULLABLE)) {
            $type = $this->parse_nullable($tokens);
        } else {
            $type = $this->parse_atomic($tokens);
            $tokens->push_save_point();
            $tokens->skip_new_line_tokens_and_consume_comments();
            try {
                $enriched_type = $this->enrich_type_on_union_or_intersection($tokens, $type);
            } catch (Parser_Exception $parser_exception) {
                $enriched_type = null;
            }
            if ($enriched_type !== null) {
                $type = $enriched_type;
                $tokens->drop_save_point();
            } else {
                $tokens->rollback();
                $type = $this->enrich_type_on_union_or_intersection($tokens, $type) ?? $type;
            }
        }
        return $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
    }
    /** @phpstan-impure */
    private function enrich_type_on_union_or_intersection(Token_Iterator $tokens, Ast\Type\Type_Node $type): ?Ast\Type\Type_Node
    {
        if ($tokens->is_current_token_type(Lexer::TOKEN_UNION)) {
            return $this->parse_union($tokens, $type);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_INTERSECTION)) {
            return $this->parse_intersection($tokens, $type);
        }
        return null;
    }
    /**
     * @internal
     * @template T of Ast\Node
     * @param T $type
     * @return T
     */
    public function enrich_with_attributes(Token_Iterator $tokens, Ast\Node $type, int $start_line, int $start_index): Ast\Node
    {
        if ($this->config->use_lines_attributes) {
            $type->set_attribute(Ast\Attribute::START_LINE, $start_line);
            $type->set_attribute(Ast\Attribute::END_LINE, $tokens->current_token_line());
        }
        $comments = $tokens->flush_comments();
        if ($this->config->use_comments_attributes) {
            $type->set_attribute(Ast\Attribute::COMMENTS, $comments);
        }
        if ($this->config->use_index_attributes) {
            $type->set_attribute(Ast\Attribute::START_INDEX, $start_index);
            $type->set_attribute(Ast\Attribute::END_INDEX, $tokens->end_index_of_last_relevant_token());
        }
        return $type;
    }
    /** @phpstan-impure */
    private function sub_parse(Token_Iterator $tokens): Ast\Type\Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_NULLABLE)) {
            $type = $this->parse_nullable($tokens);
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_VARIABLE)) {
            $type = $this->parse_conditional_for_parameter($tokens, $tokens->current_token_value());
        } else {
            $type = $this->parse_atomic($tokens);
            if ($tokens->is_current_token_value('is')) {
                $type = $this->parse_conditional($tokens, $type);
            } else {
                $tokens->skip_new_line_tokens_and_consume_comments();
                if ($tokens->is_current_token_type(Lexer::TOKEN_UNION)) {
                    $type = $this->sub_parse_union($tokens, $type);
                } elseif ($tokens->is_current_token_type(Lexer::TOKEN_INTERSECTION)) {
                    $type = $this->sub_parse_intersection($tokens, $type);
                }
            }
        }
        return $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
    }
    /** @phpstan-impure */
    private function parse_atomic(Token_Iterator $tokens): Ast\Type\Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            $type = $this->sub_parse($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
            $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                $type = $this->try_parse_array_or_offset_access($tokens, $type);
            }
            return $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
        }
        if ($tokens->try_consume_token_type(Lexer::TOKEN_THIS_VARIABLE)) {
            $type = $this->enrich_with_attributes($tokens, new Ast\Type\This_Type_Node(), $start_line, $start_index);
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                $type = $this->try_parse_array_or_offset_access($tokens, $type);
            }
            return $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
        }
        $current_token_value = $tokens->current_token_value();
        $tokens->push_save_point();
        // because of ConstFetchNode
        if ($tokens->try_consume_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $type = $this->enrich_with_attributes($tokens, new Ast\Type\Identifier_Type_Node($current_token_value), $start_line, $start_index);
            if (!$tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                $tokens->drop_save_point();
                // because of ConstFetchNode
                if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
                    $tokens->push_save_point();
                    $is_html = $this->is_html($tokens);
                    $tokens->rollback();
                    if ($is_html) {
                        return $type;
                    }
                    $orig_type = $type;
                    $type = $this->try_parse_callable($tokens, $type, true);
                    if ($type === $orig_type) {
                        $type = $this->parse_generic($tokens, $type);
                        if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                            $type = $this->try_parse_array_or_offset_access($tokens, $type);
                        }
                    }
                } elseif ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
                    $type = $this->try_parse_callable($tokens, $type, false);
                } elseif ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                    $type = $this->try_parse_array_or_offset_access($tokens, $type);
                } elseif (in_array($type->name, [Ast\Type\Array_Shape_Node::KIND_ARRAY, Ast\Type\Array_Shape_Node::KIND_LIST, Ast\Type\Array_Shape_Node::KIND_NON_EMPTY_ARRAY, Ast\Type\Array_Shape_Node::KIND_NON_EMPTY_LIST, 'object'], true) && $tokens->is_current_token_type(Lexer::TOKEN_OPEN_CURLY_BRACKET) && !$tokens->is_preceded_by_horizontal_whitespace()) {
                    if ($type->name === 'object') {
                        $type = $this->parse_object_shape($tokens);
                    } else {
                        $type = $this->parse_array_shape($tokens, $type->name);
                    }
                    if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                        $type = $this->try_parse_array_or_offset_access($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
                    }
                }
                return $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
            }
            $tokens->rollback();
            // because of ConstFetchNode
        } else {
            $tokens->drop_save_point();
            // because of ConstFetchNode
        }
        $current_token_value = $tokens->current_token_value();
        $current_token_type = $tokens->current_token_type();
        $current_token_offset = $tokens->current_token_offset();
        $current_token_line = $tokens->current_token_line();
        try {
            $const_expr = $this->const_expr_parser->parse($tokens);
            if ($const_expr instanceof Ast\Const_Expr\Const_Expr_Array_Node) {
                throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
            }
            $type = $this->enrich_with_attributes($tokens, new Ast\Type\Const_Type_Node($const_expr), $start_line, $start_index);
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->try_parse_array_or_offset_access($tokens, $type);
            }
            return $type;
        } catch (LogicException $e) {
            throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
        }
    }
    /** @phpstan-impure */
    private function parse_union(Token_Iterator $tokens, Ast\Type\Type_Node $type): Ast\Type\Type_Node
    {
        $types = [$type];
        while ($tokens->try_consume_token_type(Lexer::TOKEN_UNION)) {
            $types[] = $this->parse_atomic($tokens);
            $tokens->push_save_point();
            $tokens->skip_new_line_tokens_and_consume_comments();
            if (!$tokens->is_current_token_type(Lexer::TOKEN_UNION)) {
                $tokens->rollback();
                break;
            }
            $tokens->drop_save_point();
        }
        return new Ast\Type\Union_Type_Node($types);
    }
    /** @phpstan-impure */
    private function sub_parse_union(Token_Iterator $tokens, Ast\Type\Type_Node $type): Ast\Type\Type_Node
    {
        $types = [$type];
        while ($tokens->try_consume_token_type(Lexer::TOKEN_UNION)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            $types[] = $this->parse_atomic($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        }
        return new Ast\Type\Union_Type_Node($types);
    }
    /** @phpstan-impure */
    private function parse_intersection(Token_Iterator $tokens, Ast\Type\Type_Node $type): Ast\Type\Type_Node
    {
        $types = [$type];
        while ($tokens->try_consume_token_type(Lexer::TOKEN_INTERSECTION)) {
            $types[] = $this->parse_atomic($tokens);
            $tokens->push_save_point();
            $tokens->skip_new_line_tokens_and_consume_comments();
            if (!$tokens->is_current_token_type(Lexer::TOKEN_INTERSECTION)) {
                $tokens->rollback();
                break;
            }
            $tokens->drop_save_point();
        }
        return new Ast\Type\Intersection_Type_Node($types);
    }
    /** @phpstan-impure */
    private function sub_parse_intersection(Token_Iterator $tokens, Ast\Type\Type_Node $type): Ast\Type\Type_Node
    {
        $types = [$type];
        while ($tokens->try_consume_token_type(Lexer::TOKEN_INTERSECTION)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            $types[] = $this->parse_atomic($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        }
        return new Ast\Type\Intersection_Type_Node($types);
    }
    /** @phpstan-impure */
    private function parse_conditional(Token_Iterator $tokens, Ast\Type\Type_Node $subject_type): Ast\Type\Type_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        $negated = false;
        if ($tokens->is_current_token_value('not')) {
            $negated = true;
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        }
        $target_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_NULLABLE);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $if_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_COLON);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $else_type = $this->sub_parse($tokens);
        return new Ast\Type\Conditional_Type_Node($subject_type, $target_type, $if_type, $else_type, $negated);
    }
    /** @phpstan-impure */
    private function parse_conditional_for_parameter(Token_Iterator $tokens, string $parameter_name): Ast\Type\Type_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_VARIABLE);
        $tokens->consume_token_value(Lexer::TOKEN_IDENTIFIER, 'is');
        $negated = false;
        if ($tokens->is_current_token_value('not')) {
            $negated = true;
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        }
        $target_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_NULLABLE);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $if_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_COLON);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $else_type = $this->sub_parse($tokens);
        return new Ast\Type\Conditional_Type_For_Parameter_Node($parameter_name, $target_type, $if_type, $else_type, $negated);
    }
    /** @phpstan-impure */
    private function parse_nullable(Token_Iterator $tokens): Ast\Type\Type_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_NULLABLE);
        $type = $this->parse_atomic($tokens);
        return new Ast\Type\Nullable_Type_Node($type);
    }
    /** @phpstan-impure */
    public function is_html(Token_Iterator $tokens): bool
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        if (!$tokens->is_current_token_type(Lexer::TOKEN_IDENTIFIER)) {
            return false;
        }
        $html_tag_name = $tokens->current_token_value();
        $tokens->next();
        if (!$tokens->try_consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET)) {
            return false;
        }
        $end_tag = '</' . $html_tag_name . '>';
        $end_tag_search_offset = -strlen($end_tag);
        while (!$tokens->is_current_token_type(Lexer::TOKEN_END)) {
            if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET) && strpos($tokens->current_token_value(), '/' . $html_tag_name . '>') !== false || substr_compare($tokens->current_token_value(), $end_tag, $end_tag_search_offset) === 0) {
                return true;
            }
            $tokens->next();
        }
        return false;
    }
    /** @phpstan-impure */
    public function parse_generic(Token_Iterator $tokens, Ast\Type\Identifier_Type_Node $base_type): Ast\Type\Generic_Type_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $start_line = $base_type->get_attribute(Ast\Attribute::START_LINE);
        $start_index = $base_type->get_attribute(Ast\Attribute::START_INDEX);
        $generic_types = [];
        $variances = [];
        $is_first = true;
        while ($is_first || $tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            // trailing comma case
            if (!$is_first && $tokens->is_current_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET)) {
                break;
            }
            $is_first = false;
            [$generic_types[], $variances[]] = $this->parse_generic_type_argument($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        }
        $type = new Ast\Type\Generic_Type_Node($base_type, $generic_types, $variances);
        if ($start_line !== null && $start_index !== null) {
            $type = $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        return $type;
    }
    /**
     * @phpstan-impure
     * @return array{Ast\Type\TypeNode, Ast\Type\GenericTypeNode::VARIANCE_*}
     */
    public function parse_generic_type_argument(Token_Iterator $tokens): array
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->try_consume_token_type(Lexer::TOKEN_WILDCARD)) {
            return [$this->enrich_with_attributes($tokens, new Ast\Type\Identifier_Type_Node('mixed'), $start_line, $start_index), Ast\Type\Generic_Type_Node::VARIANCE_BIVARIANT];
        }
        if ($tokens->try_consume_token_value('contravariant')) {
            $variance = Ast\Type\Generic_Type_Node::VARIANCE_CONTRAVARIANT;
        } elseif ($tokens->try_consume_token_value('covariant')) {
            $variance = Ast\Type\Generic_Type_Node::VARIANCE_COVARIANT;
        } else {
            $variance = Ast\Type\Generic_Type_Node::VARIANCE_INVARIANT;
        }
        $type = $this->parse($tokens);
        return [$type, $variance];
    }
    /**
     * @throws ParserException
     * @param ?callable(TokenIterator): string $parseDescription
     */
    public function parse_template_tag_value(Token_Iterator $tokens, ?callable $parse_description = null): Template_Tag_Value_Node
    {
        $name = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        $upper_bound = $lower_bound = null;
        if ($tokens->try_consume_token_value('of') || $tokens->try_consume_token_value('as')) {
            $upper_bound = $this->parse($tokens);
        }
        if ($tokens->try_consume_token_value('super')) {
            $lower_bound = $this->parse($tokens);
        }
        if ($tokens->try_consume_token_value('=')) {
            $default = $this->parse($tokens);
        } else {
            $default = null;
        }
        if ($parse_description !== null) {
            $description = $parse_description($tokens);
        } else {
            $description = '';
        }
        if ($name === '') {
            throw new LogicException('Template tag name cannot be empty.');
        }
        return new Ast\Php_Doc\Template_Tag_Value_Node($name, $upper_bound, $description, $default, $lower_bound);
    }
    /** @phpstan-impure */
    private function parse_callable(Token_Iterator $tokens, Ast\Type\Identifier_Type_Node $identifier, bool $has_template): Ast\Type\Type_Node
    {
        $templates = $has_template ? $this->parse_callable_templates($tokens) : [];
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $parameters = [];
        if (!$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PARENTHESES)) {
            $parameters[] = $this->parse_callable_parameter($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
            while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
                $tokens->skip_new_line_tokens_and_consume_comments();
                if ($tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PARENTHESES)) {
                    break;
                }
                $parameters[] = $this->parse_callable_parameter($tokens);
                $tokens->skip_new_line_tokens_and_consume_comments();
            }
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
        $tokens->consume_token_type(Lexer::TOKEN_COLON);
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $return_type = $this->enrich_with_attributes($tokens, $this->parse_callable_return_type($tokens), $start_line, $start_index);
        return new Ast\Type\Callable_Type_Node($identifier, $parameters, $return_type, $templates);
    }
    /**
     * @return Ast\PhpDoc\TemplateTagValueNode[]
     *
     * @phpstan-impure
     */
    private function parse_callable_templates(Token_Iterator $tokens): array
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        $templates = [];
        $is_first = true;
        while ($is_first || $tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            // trailing comma case
            if (!$is_first && $tokens->is_current_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET)) {
                break;
            }
            $is_first = false;
            $templates[] = $this->parse_callable_template_argument($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        return $templates;
    }
    private function parse_callable_template_argument(Token_Iterator $tokens): Ast\Php_Doc\Template_Tag_Value_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        return $this->enrich_with_attributes($tokens, $this->parse_template_tag_value($tokens), $start_line, $start_index);
    }
    /** @phpstan-impure */
    private function parse_callable_parameter(Token_Iterator $tokens): Ast\Type\Callable_Type_Parameter_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $type = $this->parse($tokens);
        $is_reference = $tokens->try_consume_token_type(Lexer::TOKEN_REFERENCE);
        $is_variadic = $tokens->try_consume_token_type(Lexer::TOKEN_VARIADIC);
        if ($tokens->is_current_token_type(Lexer::TOKEN_VARIABLE)) {
            $parameter_name = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_VARIABLE);
        } else {
            $parameter_name = '';
        }
        $is_optional = $tokens->try_consume_token_type(Lexer::TOKEN_EQUAL);
        return $this->enrich_with_attributes($tokens, new Ast\Type\Callable_Type_Parameter_Node($type, $is_reference, $is_variadic, $parameter_name, $is_optional), $start_line, $start_index);
    }
    /** @phpstan-impure */
    private function parse_callable_return_type(Token_Iterator $tokens): Ast\Type\Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_NULLABLE)) {
            return $this->parse_nullable($tokens);
        }
        if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
            $type = $this->sub_parse($tokens);
            $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->try_parse_array_or_offset_access($tokens, $type);
            }
            return $type;
        }
        if ($tokens->try_consume_token_type(Lexer::TOKEN_THIS_VARIABLE)) {
            $type = new Ast\Type\This_Type_Node();
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->try_parse_array_or_offset_access($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
            }
            return $type;
        }
        $current_token_value = $tokens->current_token_value();
        $tokens->push_save_point();
        // because of ConstFetchNode
        if ($tokens->try_consume_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $type = new Ast\Type\Identifier_Type_Node($current_token_value);
            if (!$tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
                    $type = $this->parse_generic($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
                    if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                        $type = $this->try_parse_array_or_offset_access($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
                    }
                } elseif ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                    $type = $this->try_parse_array_or_offset_access($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
                } elseif (in_array($type->name, [Ast\Type\Array_Shape_Node::KIND_ARRAY, Ast\Type\Array_Shape_Node::KIND_LIST, Ast\Type\Array_Shape_Node::KIND_NON_EMPTY_ARRAY, Ast\Type\Array_Shape_Node::KIND_NON_EMPTY_LIST, 'object'], true) && $tokens->is_current_token_type(Lexer::TOKEN_OPEN_CURLY_BRACKET) && !$tokens->is_preceded_by_horizontal_whitespace()) {
                    if ($type->name === 'object') {
                        $type = $this->parse_object_shape($tokens);
                    } else {
                        $type = $this->parse_array_shape($tokens, $type->name);
                    }
                    if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                        $type = $this->try_parse_array_or_offset_access($tokens, $this->enrich_with_attributes($tokens, $type, $start_line, $start_index));
                    }
                }
                return $type;
            }
            $tokens->rollback();
            // because of ConstFetchNode
        } else {
            $tokens->drop_save_point();
            // because of ConstFetchNode
        }
        $current_token_value = $tokens->current_token_value();
        $current_token_type = $tokens->current_token_type();
        $current_token_offset = $tokens->current_token_offset();
        $current_token_line = $tokens->current_token_line();
        try {
            $const_expr = $this->const_expr_parser->parse($tokens);
            if ($const_expr instanceof Ast\Const_Expr\Const_Expr_Array_Node) {
                throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
            }
            $type = $this->enrich_with_attributes($tokens, new Ast\Type\Const_Type_Node($const_expr), $start_line, $start_index);
            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->try_parse_array_or_offset_access($tokens, $type);
            }
            return $type;
        } catch (LogicException $e) {
            throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
        }
    }
    /** @phpstan-impure */
    private function try_parse_callable(Token_Iterator $tokens, Ast\Type\Identifier_Type_Node $identifier, bool $has_template): Ast\Type\Type_Node
    {
        try {
            $tokens->push_save_point();
            $type = $this->parse_callable($tokens, $identifier, $has_template);
            $tokens->drop_save_point();
        } catch (Parser_Exception $e) {
            $tokens->rollback();
            $type = $identifier;
        }
        return $type;
    }
    /** @phpstan-impure */
    private function try_parse_array_or_offset_access(Token_Iterator $tokens, Ast\Type\Type_Node $type): Ast\Type\Type_Node
    {
        $start_line = $type->get_attribute(Ast\Attribute::START_LINE);
        $start_index = $type->get_attribute(Ast\Attribute::START_INDEX);
        try {
            while ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                $tokens->push_save_point();
                $can_be_offset_access_type = !$tokens->is_preceded_by_horizontal_whitespace();
                $tokens->consume_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET);
                if ($can_be_offset_access_type && !$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_SQUARE_BRACKET)) {
                    $offset = $this->parse($tokens);
                    $tokens->consume_token_type(Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
                    $tokens->drop_save_point();
                    $type = new Ast\Type\Offset_Access_Type_Node($type, $offset);
                    if ($start_line !== null && $start_index !== null) {
                        $type = $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
                    }
                } else {
                    $tokens->consume_token_type(Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
                    $tokens->drop_save_point();
                    $type = new Ast\Type\Array_Type_Node($type);
                    if ($start_line !== null && $start_index !== null) {
                        $type = $this->enrich_with_attributes($tokens, $type, $start_line, $start_index);
                    }
                }
            }
        } catch (Parser_Exception $e) {
            $tokens->rollback();
        }
        return $type;
    }
    /**
     * @phpstan-impure
     * @param Ast\Type\ArrayShapeNode::KIND_* $kind
     */
    private function parse_array_shape(Token_Iterator $tokens, string $kind): Ast\Type\Array_Shape_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_CURLY_BRACKET);
        $items = [];
        $sealed = true;
        $unsealed_type = null;
        $done = false;
        do {
            $tokens->skip_new_line_tokens_and_consume_comments();
            if ($tokens->try_consume_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET)) {
                return Ast\Type\Array_Shape_Node::create_sealed($items, $kind);
            }
            if ($tokens->try_consume_token_type(Lexer::TOKEN_VARIADIC)) {
                $sealed = false;
                $tokens->skip_new_line_tokens_and_consume_comments();
                if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
                    if ($kind === Ast\Type\Array_Shape_Node::KIND_ARRAY) {
                        $unsealed_type = $this->parse_array_shape_unsealed_type($tokens);
                    } else {
                        $unsealed_type = $this->parse_list_shape_unsealed_type($tokens);
                    }
                    $tokens->skip_new_line_tokens_and_consume_comments();
                }
                $tokens->try_consume_token_type(Lexer::TOKEN_COMMA);
                break;
            }
            $items[] = $this->parse_array_shape_item($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
            if (!$tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
                $done = true;
            }
            if ($tokens->current_token_type() !== Lexer::TOKEN_COMMENT) {
                continue;
            }
            $tokens->next();
        } while (!$done);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET);
        if ($sealed) {
            return Ast\Type\Array_Shape_Node::create_sealed($items, $kind);
        }
        return Ast\Type\Array_Shape_Node::create_unsealed($items, $unsealed_type, $kind);
    }
    /** @phpstan-impure */
    private function parse_array_shape_item(Token_Iterator $tokens): Ast\Type\Array_Shape_Item_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        // parse any comments above the item
        $tokens->skip_new_line_tokens_and_consume_comments();
        try {
            $tokens->push_save_point();
            $key = $this->parse_array_shape_key($tokens);
            $optional = $tokens->try_consume_token_type(Lexer::TOKEN_NULLABLE);
            $tokens->consume_token_type(Lexer::TOKEN_COLON);
            $value = $this->parse($tokens);
            $tokens->drop_save_point();
            return $this->enrich_with_attributes($tokens, new Ast\Type\Array_Shape_Item_Node($key, $optional, $value), $start_line, $start_index);
        } catch (Parser_Exception $e) {
            $tokens->rollback();
            $value = $this->parse($tokens);
            return $this->enrich_with_attributes($tokens, new Ast\Type\Array_Shape_Item_Node(null, false, $value), $start_line, $start_index);
        }
    }
    /**
     * @phpstan-impure
     * @return Ast\ConstExpr\ConstExprIntegerNode|Ast\ConstExpr\ConstExprStringNode|Ast\ConstExpr\ConstFetchNode|Ast\Type\IdentifierTypeNode
     */
    private function parse_array_shape_key(Token_Iterator $tokens): \Php_Stan\Php_Doc_Parser\Ast\Node
    {
        $start_index = $tokens->current_token_index();
        $start_line = $tokens->current_token_line();
        if ($tokens->is_current_token_type(Lexer::TOKEN_INTEGER)) {
            $key = new Ast\Const_Expr\Const_Expr_Integer_Node(str_replace('_', '', $tokens->current_token_value()));
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_SINGLE_QUOTED_STRING)) {
            $key = new Ast\Const_Expr\Const_Expr_String_Node(String_Unescaper::unescape_string($tokens->current_token_value()), Ast\Const_Expr\Const_Expr_String_Node::SINGLE_QUOTED);
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_QUOTED_STRING)) {
            $key = new Ast\Const_Expr\Const_Expr_String_Node(String_Unescaper::unescape_string($tokens->current_token_value()), Ast\Const_Expr\Const_Expr_String_Node::DOUBLE_QUOTED);
            $tokens->next();
        } else {
            $identifier = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
            if ($tokens->try_consume_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                $class_constant_name = $tokens->current_token_value();
                $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
                $key = new Ast\Const_Expr\Const_Fetch_Node($identifier, $class_constant_name);
            } else {
                $key = new Ast\Type\Identifier_Type_Node($identifier);
            }
        }
        return $this->enrich_with_attributes($tokens, $key, $start_line, $start_index);
    }
    /**
     * @phpstan-impure
     */
    private function parse_array_shape_unsealed_type(Token_Iterator $tokens): Ast\Type\Array_Shape_Unsealed_Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $value_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $key_type = null;
        if ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
            $tokens->skip_new_line_tokens_and_consume_comments();
            $key_type = $value_type;
            $value_type = $this->parse($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        return $this->enrich_with_attributes($tokens, new Ast\Type\Array_Shape_Unsealed_Type_Node($value_type, $key_type), $start_line, $start_index);
    }
    /**
     * @phpstan-impure
     */
    private function parse_list_shape_unsealed_type(Token_Iterator $tokens): Ast\Type\Array_Shape_Unsealed_Type_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $value_type = $this->parse($tokens);
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        return $this->enrich_with_attributes($tokens, new Ast\Type\Array_Shape_Unsealed_Type_Node($value_type, null), $start_line, $start_index);
    }
    /**
     * @phpstan-impure
     */
    private function parse_object_shape(Token_Iterator $tokens): Ast\Type\Object_Shape_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_CURLY_BRACKET);
        $items = [];
        do {
            $tokens->skip_new_line_tokens_and_consume_comments();
            if ($tokens->try_consume_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET)) {
                return new Ast\Type\Object_Shape_Node($items);
            }
            $items[] = $this->parse_object_shape_item($tokens);
            $tokens->skip_new_line_tokens_and_consume_comments();
        } while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA));
        $tokens->skip_new_line_tokens_and_consume_comments();
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET);
        return new Ast\Type\Object_Shape_Node($items);
    }
    /** @phpstan-impure */
    private function parse_object_shape_item(Token_Iterator $tokens): Ast\Type\Object_Shape_Item_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $tokens->skip_new_line_tokens_and_consume_comments();
        $key = $this->parse_object_shape_key($tokens);
        $optional = $tokens->try_consume_token_type(Lexer::TOKEN_NULLABLE);
        $tokens->consume_token_type(Lexer::TOKEN_COLON);
        $value = $this->parse($tokens);
        return $this->enrich_with_attributes($tokens, new Ast\Type\Object_Shape_Item_Node($key, $optional, $value), $start_line, $start_index);
    }
    /**
     * @phpstan-impure
     * @return Ast\ConstExpr\ConstExprStringNode|Ast\Type\IdentifierTypeNode
     */
    private function parse_object_shape_key(Token_Iterator $tokens): \Php_Stan\Php_Doc_Parser\Ast\Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_SINGLE_QUOTED_STRING)) {
            $key = new Ast\Const_Expr\Const_Expr_String_Node(String_Unescaper::unescape_string($tokens->current_token_value()), Ast\Const_Expr\Const_Expr_String_Node::SINGLE_QUOTED);
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_QUOTED_STRING)) {
            $key = new Ast\Const_Expr\Const_Expr_String_Node(String_Unescaper::unescape_string($tokens->current_token_value()), Ast\Const_Expr\Const_Expr_String_Node::DOUBLE_QUOTED);
            $tokens->next();
        } else {
            $key = new Ast\Type\Identifier_Type_Node($tokens->current_token_value());
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        }
        return $this->enrich_with_attributes($tokens, $key, $start_line, $start_index);
    }
}