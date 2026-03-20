<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Parser;

use function array_key_exists;
use function count;
use LogicException;
use Php_Stan\Php_Doc_Parser\Ast;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_Integer_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Expr_String_Node;
use Php_Stan\Php_Doc_Parser\Ast\Const_Expr\Const_Fetch_Node;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine;
use Php_Stan\Php_Doc_Parser\Ast\Type\Identifier_Type_Node;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use Php_Stan\Php_Doc_Parser\Parser_Config;
use Php_Stan\Should_Not_Happen_Exception;
use function rtrim;
use function str_replace;
use function trim;
/**
 * @phpstan-import-type ValueType from Doctrine\DoctrineArgument as DoctrineValueType
 */
class Php_Doc_Parser
{
    private const DISALLOWED_DESCRIPTION_START_TOKENS = [Lexer::TOKEN_UNION, Lexer::TOKEN_INTERSECTION];
    private Parser_Config $config;
    private Type_Parser $type_parser;
    private Const_Expr_Parser $constant_expr_parser;
    private Const_Expr_Parser $doctrine_constant_expr_parser;
    public function __construct(Parser_Config $config, Type_Parser $type_parser, Const_Expr_Parser $constant_expr_parser)
    {
        $this->config = $config;
        $this->type_parser = $type_parser;
        $this->constant_expr_parser = $constant_expr_parser;
        $this->doctrine_constant_expr_parser = $constant_expr_parser->to_doctrine();
    }
    public function parse(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Node
    {
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_PHPDOC);
        $tokens->try_consume_token_type(Lexer::TOKEN_PHPDOC_EOL);
        $children = [];
        if (!$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PHPDOC)) {
            $last_child = $this->parse_child($tokens);
            $children[] = $last_child;
            while (!$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PHPDOC)) {
                if ($last_child instanceof Ast\Php_Doc\Php_Doc_Tag_Node && ($last_child->value instanceof Doctrine\Doctrine_Tag_Value_Node || $last_child->value instanceof Ast\Php_Doc\Generic_Tag_Value_Node)) {
                    $tokens->try_consume_token_type(Lexer::TOKEN_PHPDOC_EOL);
                    if ($tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PHPDOC)) {
                        break;
                    }
                    $last_child = $this->parse_child($tokens);
                    $children[] = $last_child;
                    continue;
                }
                if (!$tokens->try_consume_token_type(Lexer::TOKEN_PHPDOC_EOL)) {
                    break;
                }
                if ($tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PHPDOC)) {
                    break;
                }
                $last_child = $this->parse_child($tokens);
                $children[] = $last_child;
            }
        }
        try {
            $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PHPDOC);
        } catch (Parser_Exception $e) {
            $name = '';
            $start_line = $tokens->current_token_line();
            $start_index = $tokens->current_token_index();
            if (count($children) > 0) {
                $last_child = $children[count($children) - 1];
                if ($last_child instanceof Ast\Php_Doc\Php_Doc_Tag_Node) {
                    $name = $last_child->name;
                    $start_line = $tokens->current_token_line();
                    $start_index = $tokens->current_token_index();
                }
            }
            $tag = new Ast\Php_Doc\Php_Doc_Tag_Node($name, $this->enrich_with_attributes($tokens, new Ast\Php_Doc\Invalid_Tag_Value_Node($e->get_message(), $e), $start_line, $start_index));
            $tokens->forward_to_the_end();
            $comments = $tokens->flush_comments();
            if ($comments !== []) {
                throw new LogicException('Comments should already be flushed');
            }
            return $this->enrich_with_attributes($tokens, new Ast\Php_Doc\Php_Doc_Node([$this->enrich_with_attributes($tokens, $tag, $start_line, $start_index)]), 1, 0);
        }
        $comments = $tokens->flush_comments();
        if ($comments !== []) {
            throw new LogicException('Comments should already be flushed');
        }
        return $this->enrich_with_attributes($tokens, new Ast\Php_Doc\Php_Doc_Node($children), 1, 0);
    }
    /** @phpstan-impure */
    private function parse_child(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Child_Node
    {
        if ($tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_TAG)) {
            $start_line = $tokens->current_token_line();
            $start_index = $tokens->current_token_index();
            return $this->enrich_with_attributes($tokens, $this->parse_tag($tokens), $start_line, $start_index);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_DOCTRINE_TAG)) {
            $start_line = $tokens->current_token_line();
            $start_index = $tokens->current_token_index();
            $tag = $tokens->current_token_value();
            $tokens->next();
            $tag_start_line = $tokens->current_token_line();
            $tag_start_index = $tokens->current_token_index();
            return $this->enrich_with_attributes($tokens, new Ast\Php_Doc\Php_Doc_Tag_Node($tag, $this->enrich_with_attributes($tokens, $this->parse_doctrine_tag_value($tokens, $tag), $tag_start_line, $tag_start_index)), $start_line, $start_index);
        }
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $text = $this->parse_text($tokens);
        return $this->enrich_with_attributes($tokens, $text, $start_line, $start_index);
    }
    /**
     * @template T of Ast\Node
     * @param T $tag
     * @return T
     */
    private function enrich_with_attributes(Token_Iterator $tokens, Ast\Node $tag, int $start_line, int $start_index): Ast\Node
    {
        if ($this->config->use_lines_attributes) {
            $tag->set_attribute(Ast\Attribute::START_LINE, $start_line);
            $tag->set_attribute(Ast\Attribute::END_LINE, $tokens->current_token_line());
        }
        if ($this->config->use_index_attributes) {
            $tag->set_attribute(Ast\Attribute::START_INDEX, $start_index);
            $tag->set_attribute(Ast\Attribute::END_INDEX, $tokens->end_index_of_last_relevant_token());
        }
        return $tag;
    }
    private function parse_text(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Text_Node
    {
        $text = '';
        $end_tokens = [Lexer::TOKEN_CLOSE_PHPDOC, Lexer::TOKEN_END];
        $savepoint = false;
        // if the next token is EOL, everything below is skipped and empty string is returned
        while (true) {
            $tmp_text = $tokens->get_skipped_horizontal_white_space_if_any() . $tokens->join_until(Lexer::TOKEN_PHPDOC_EOL, ...$end_tokens);
            $text .= $tmp_text;
            // stop if we're not at EOL - meaning it's the end of PHPDoc
            if (!$tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL, Lexer::TOKEN_CLOSE_PHPDOC)) {
                break;
            }
            if (!$savepoint) {
                $tokens->push_save_point();
                $savepoint = true;
            } elseif ($tmp_text !== '') {
                $tokens->drop_save_point();
                $tokens->push_save_point();
            }
            $tokens->push_save_point();
            $tokens->next();
            // if we're at EOL, check what's next
            // if next is a PHPDoc tag, EOL, or end of PHPDoc, stop
            if ($tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_TAG, Lexer::TOKEN_DOCTRINE_TAG, ...$end_tokens)) {
                $tokens->rollback();
                break;
            }
            // otherwise if the next is text, continue building the description string
            $tokens->drop_save_point();
            $text .= $tokens->get_detected_newline() ?? "\n";
        }
        if ($savepoint) {
            $tokens->rollback();
            $text = rtrim($text, $tokens->get_detected_newline() ?? "\n");
        }
        return new Ast\Php_Doc\Php_Doc_Text_Node(trim($text, " \t"));
    }
    private function parse_optional_description_after_doctrine_tag(Token_Iterator $tokens): string
    {
        $text = '';
        $end_tokens = [Lexer::TOKEN_CLOSE_PHPDOC, Lexer::TOKEN_END];
        $savepoint = false;
        // if the next token is EOL, everything below is skipped and empty string is returned
        while (true) {
            $tmp_text = $tokens->get_skipped_horizontal_white_space_if_any() . $tokens->join_until(Lexer::TOKEN_PHPDOC_TAG, Lexer::TOKEN_DOCTRINE_TAG, Lexer::TOKEN_PHPDOC_EOL, ...$end_tokens);
            $text .= $tmp_text;
            // stop if we're not at EOL - meaning it's the end of PHPDoc
            if (!$tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL, Lexer::TOKEN_CLOSE_PHPDOC)) {
                if (!$tokens->is_preceded_by_horizontal_whitespace()) {
                    return trim($text . $this->parse_text($tokens)->text, " \t");
                }
                if ($tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_TAG)) {
                    $tokens->push_save_point();
                    $child = $this->parse_child($tokens);
                    if ($child instanceof Ast\Php_Doc\Php_Doc_Tag_Node) {
                        if ($child->value instanceof Ast\Php_Doc\Generic_Tag_Value_Node || $child->value instanceof Doctrine\Doctrine_Tag_Value_Node) {
                            $tokens->rollback();
                            break;
                        }
                        if ($child->value instanceof Ast\Php_Doc\Invalid_Tag_Value_Node) {
                            $tokens->rollback();
                            $tokens->push_save_point();
                            $tokens->next();
                            if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
                                $tokens->rollback();
                                break;
                            }
                            $tokens->rollback();
                            return trim($text . $this->parse_text($tokens)->text, " \t");
                        }
                    }
                    $tokens->rollback();
                    return trim($text . $this->parse_text($tokens)->text, " \t");
                }
                break;
            }
            if (!$savepoint) {
                $tokens->push_save_point();
                $savepoint = true;
            } elseif ($tmp_text !== '') {
                $tokens->drop_save_point();
                $tokens->push_save_point();
            }
            $tokens->push_save_point();
            $tokens->next();
            // if we're at EOL, check what's next
            // if next is a PHPDoc tag, EOL, or end of PHPDoc, stop
            if ($tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_TAG, Lexer::TOKEN_DOCTRINE_TAG, ...$end_tokens)) {
                $tokens->rollback();
                break;
            }
            // otherwise if the next is text, continue building the description string
            $tokens->drop_save_point();
            $text .= $tokens->get_detected_newline() ?? "\n";
        }
        if ($savepoint) {
            $tokens->rollback();
            $text = rtrim($text, $tokens->get_detected_newline() ?? "\n");
        }
        return trim($text, " \t");
    }
    public function parse_tag(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Tag_Node
    {
        $tag = $tokens->current_token_value();
        $tokens->next();
        $value = $this->parse_tag_value($tokens, $tag);
        return new Ast\Php_Doc\Php_Doc_Tag_Node($tag, $value);
    }
    public function parse_tag_value(Token_Iterator $tokens, string $tag): Ast\Php_Doc\Php_Doc_Tag_Value_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        try {
            $tokens->push_save_point();
            switch ($tag) {
                case '@param':
                case '@phpstan-param':
                case '@psalm-param':
                case '@phan-param':
                    $tag_value = $this->parse_param_tag_value($tokens);
                    break;
                case '@param-immediately-invoked-callable':
                case '@phpstan-param-immediately-invoked-callable':
                    $tag_value = $this->parse_param_immediately_invoked_callable_tag_value($tokens);
                    break;
                case '@param-later-invoked-callable':
                case '@phpstan-param-later-invoked-callable':
                    $tag_value = $this->parse_param_later_invoked_callable_tag_value($tokens);
                    break;
                case '@param-closure-this':
                case '@phpstan-param-closure-this':
                    $tag_value = $this->parse_param_closure_this_tag_value($tokens);
                    break;
                case '@pure-unless-callable-is-impure':
                case '@phpstan-pure-unless-callable-is-impure':
                    $tag_value = $this->parse_pure_unless_callable_is_impure_tag_value($tokens);
                    break;
                case '@var':
                case '@phpstan-var':
                case '@psalm-var':
                case '@phan-var':
                    $tag_value = $this->parse_var_tag_value($tokens);
                    break;
                case '@return':
                case '@phpstan-return':
                case '@psalm-return':
                case '@phan-return':
                case '@phan-real-return':
                    $tag_value = $this->parse_return_tag_value($tokens);
                    break;
                case '@throws':
                case '@phpstan-throws':
                    $tag_value = $this->parse_throws_tag_value($tokens);
                    break;
                case '@mixin':
                case '@phan-mixin':
                    $tag_value = $this->parse_mixin_tag_value($tokens);
                    break;
                case '@psalm-require-extends':
                case '@phpstan-require-extends':
                    $tag_value = $this->parse_require_extends_tag_value($tokens);
                    break;
                case '@psalm-require-implements':
                case '@phpstan-require-implements':
                    $tag_value = $this->parse_require_implements_tag_value($tokens);
                    break;
                case '@psalm-inheritors':
                case '@phpstan-sealed':
                    $tag_value = $this->parse_sealed_tag_value($tokens);
                    break;
                case '@deprecated':
                    $tag_value = $this->parse_deprecated_tag_value($tokens);
                    break;
                case '@property':
                case '@property-read':
                case '@property-write':
                case '@phpstan-property':
                case '@phpstan-property-read':
                case '@phpstan-property-write':
                case '@psalm-property':
                case '@psalm-property-read':
                case '@psalm-property-write':
                case '@phan-property':
                case '@phan-property-read':
                case '@phan-property-write':
                    $tag_value = $this->parse_property_tag_value($tokens);
                    break;
                case '@method':
                case '@phpstan-method':
                case '@psalm-method':
                case '@phan-method':
                    $tag_value = $this->parse_method_tag_value($tokens);
                    break;
                case '@template':
                case '@phpstan-template':
                case '@psalm-template':
                case '@phan-template':
                case '@template-covariant':
                case '@phpstan-template-covariant':
                case '@psalm-template-covariant':
                case '@template-contravariant':
                case '@phpstan-template-contravariant':
                case '@psalm-template-contravariant':
                    $tag_value = $this->type_parser->parse_template_tag_value($tokens, fn(\Php_Stan\Php_Doc_Parser\Parser\Token_Iterator $tokens): string => $this->parse_optional_description($tokens, true));
                    break;
                case '@extends':
                case '@phpstan-extends':
                case '@phan-extends':
                case '@phan-inherits':
                case '@template-extends':
                    $tag_value = $this->parse_extends_tag_value('@extends', $tokens);
                    break;
                case '@implements':
                case '@phpstan-implements':
                case '@template-implements':
                    $tag_value = $this->parse_extends_tag_value('@implements', $tokens);
                    break;
                case '@use':
                case '@phpstan-use':
                case '@template-use':
                    $tag_value = $this->parse_extends_tag_value('@use', $tokens);
                    break;
                case '@phpstan-type':
                case '@psalm-type':
                case '@phan-type':
                    $tag_value = $this->parse_type_alias_tag_value($tokens);
                    break;
                case '@phpstan-import-type':
                case '@psalm-import-type':
                    $tag_value = $this->parse_type_alias_import_tag_value($tokens);
                    break;
                case '@phpstan-assert':
                case '@phpstan-assert-if-true':
                case '@phpstan-assert-if-false':
                case '@psalm-assert':
                case '@psalm-assert-if-true':
                case '@psalm-assert-if-false':
                case '@phan-assert':
                case '@phan-assert-if-true':
                case '@phan-assert-if-false':
                    $tag_value = $this->parse_assert_tag_value($tokens);
                    break;
                case '@phpstan-this-out':
                case '@phpstan-self-out':
                case '@psalm-this-out':
                case '@psalm-self-out':
                    $tag_value = $this->parse_self_out_tag_value($tokens);
                    break;
                case '@param-out':
                case '@phpstan-param-out':
                case '@psalm-param-out':
                    $tag_value = $this->parse_param_out_tag_value($tokens);
                    break;
                default:
                    if ($tokens->is_current_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
                        $tag_value = $this->parse_doctrine_tag_value($tokens, $tag);
                    } else {
                        $tag_value = new Ast\Php_Doc\Generic_Tag_Value_Node($this->parse_optional_description_after_doctrine_tag($tokens));
                    }
                    break;
            }
            $tokens->drop_save_point();
        } catch (Parser_Exception $e) {
            $tokens->rollback();
            $tag_value = new Ast\Php_Doc\Invalid_Tag_Value_Node($this->parse_optional_description($tokens, false), $e);
        }
        return $this->enrich_with_attributes($tokens, $tag_value, $start_line, $start_index);
    }
    private function parse_doctrine_tag_value(Token_Iterator $tokens, string $tag): Ast\Php_Doc\Php_Doc_Tag_Value_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        return new Doctrine\Doctrine_Tag_Value_Node($this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Annotation($tag, $this->parse_doctrine_arguments($tokens, false)), $start_line, $start_index), $this->parse_optional_description_after_doctrine_tag($tokens));
    }
    /**
     * @return list<Doctrine\DoctrineArgument>
     */
    private function parse_doctrine_arguments(Token_Iterator $tokens, bool $deep): array
    {
        if (!$tokens->is_current_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
            return [];
        }
        if (!$deep) {
            $tokens->add_end_of_line_to_skipped_tokens();
        }
        $arguments = [];
        try {
            $tokens->consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES);
            do {
                if ($tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PARENTHESES)) {
                    break;
                }
                $arguments[] = $this->parse_doctrine_argument($tokens);
            } while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA));
        } finally {
            if (!$deep) {
                $tokens->remove_end_of_line_from_skipped_tokens();
            }
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
        return $arguments;
    }
    private function parse_doctrine_argument(Token_Iterator $tokens): Doctrine\Doctrine_Argument
    {
        if (!$tokens->is_current_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $start_line = $tokens->current_token_line();
            $start_index = $tokens->current_token_index();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Argument(null, $this->parse_doctrine_argument_value($tokens)), $start_line, $start_index);
        }
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        try {
            $tokens->push_save_point();
            $current_value = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
            $key = $this->enrich_with_attributes($tokens, new Identifier_Type_Node($current_value), $start_line, $start_index);
            $tokens->consume_token_type(Lexer::TOKEN_EQUAL);
            $value = $this->parse_doctrine_argument_value($tokens);
            $tokens->drop_save_point();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Argument($key, $value), $start_line, $start_index);
        } catch (Parser_Exception $e) {
            $tokens->rollback();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Argument(null, $this->parse_doctrine_argument_value($tokens)), $start_line, $start_index);
        }
    }
    /**
     * @return DoctrineValueType
     */
    private function parse_doctrine_argument_value(Token_Iterator $tokens)
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_TAG, Lexer::TOKEN_DOCTRINE_TAG)) {
            $name = $tokens->current_token_value();
            $tokens->next();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Annotation($name, $this->parse_doctrine_arguments($tokens, true)), $start_line, $start_index);
        }
        if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_CURLY_BRACKET)) {
            $items = [];
            do {
                if ($tokens->is_current_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET)) {
                    break;
                }
                $items[] = $this->parse_doctrine_array_item($tokens);
            } while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA));
            $tokens->consume_token_type(Lexer::TOKEN_CLOSE_CURLY_BRACKET);
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Array($items), $start_line, $start_index);
        }
        $current_token_value = $tokens->current_token_value();
        $tokens->push_save_point();
        // because of ConstFetchNode
        if ($tokens->try_consume_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $identifier = $this->enrich_with_attributes($tokens, new Ast\Type\Identifier_Type_Node($current_token_value), $start_line, $start_index);
            if (!$tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                $tokens->drop_save_point();
                return $identifier;
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
            $const_expr = $this->doctrine_constant_expr_parser->parse($tokens);
            if ($const_expr instanceof Ast\Const_Expr\Const_Expr_Array_Node) {
                throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
            }
            return $const_expr;
        } catch (LogicException $e) {
            throw new Parser_Exception($current_token_value, $current_token_type, $current_token_offset, Lexer::TOKEN_IDENTIFIER, null, $current_token_line);
        }
    }
    private function parse_doctrine_array_item(Token_Iterator $tokens): Doctrine\Doctrine_Array_Item
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        try {
            $tokens->push_save_point();
            $key = $this->parse_doctrine_array_key($tokens);
            if (!$tokens->try_consume_token_type(Lexer::TOKEN_EQUAL)) {
                if (!$tokens->try_consume_token_type(Lexer::TOKEN_COLON)) {
                    $tokens->consume_token_type(Lexer::TOKEN_EQUAL);
                    // will throw exception
                }
            }
            $value = $this->parse_doctrine_argument_value($tokens);
            $tokens->drop_save_point();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Array_Item($key, $value), $start_line, $start_index);
        } catch (Parser_Exception $e) {
            $tokens->rollback();
            return $this->enrich_with_attributes($tokens, new Doctrine\Doctrine_Array_Item(null, $this->parse_doctrine_argument_value($tokens)), $start_line, $start_index);
        }
    }
    /**
     * @return ConstExprIntegerNode|ConstExprStringNode|IdentifierTypeNode|ConstFetchNode
     */
    private function parse_doctrine_array_key(Token_Iterator $tokens)
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_INTEGER)) {
            $key = new Ast\Const_Expr\Const_Expr_Integer_Node(str_replace('_', '', $tokens->current_token_value()));
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_DOCTRINE_ANNOTATION_STRING)) {
            $key = $this->doctrine_constant_expr_parser->parse_doctrine_string($tokens->current_token_value(), $tokens);
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_SINGLE_QUOTED_STRING)) {
            $key = new Ast\Const_Expr\Const_Expr_String_Node(String_Unescaper::unescape_string($tokens->current_token_value()), Ast\Const_Expr\Const_Expr_String_Node::SINGLE_QUOTED);
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_QUOTED_STRING)) {
            $value = $tokens->current_token_value();
            $tokens->next();
            $key = $this->doctrine_constant_expr_parser->parse_doctrine_string($value, $tokens);
        } else {
            $current_token_value = $tokens->current_token_value();
            $tokens->push_save_point();
            // because of ConstFetchNode
            if (!$tokens->try_consume_token_type(Lexer::TOKEN_IDENTIFIER)) {
                $tokens->drop_save_point();
                throw new Parser_Exception($tokens->current_token_value(), $tokens->current_token_type(), $tokens->current_token_offset(), Lexer::TOKEN_IDENTIFIER, null, $tokens->current_token_line());
            }
            if (!$tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                $tokens->drop_save_point();
                return $this->enrich_with_attributes($tokens, new Identifier_Type_Node($current_token_value), $start_line, $start_index);
            }
            $tokens->rollback();
            $const_expr = $this->doctrine_constant_expr_parser->parse($tokens);
            if (!$const_expr instanceof Ast\Const_Expr\Const_Fetch_Node) {
                throw new Parser_Exception($tokens->current_token_value(), $tokens->current_token_type(), $tokens->current_token_offset(), Lexer::TOKEN_IDENTIFIER, null, $tokens->current_token_line());
            }
            return $const_expr;
        }
        return $this->enrich_with_attributes($tokens, $key, $start_line, $start_index);
    }
    /**
     * @return Ast\PhpDoc\ParamTagValueNode|Ast\PhpDoc\TypelessParamTagValueNode
     */
    private function parse_param_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Tag_Value_Node
    {
        if ($tokens->is_current_token_type(Lexer::TOKEN_REFERENCE, Lexer::TOKEN_VARIADIC, Lexer::TOKEN_VARIABLE)) {
            $type = null;
        } else {
            $type = $this->type_parser->parse($tokens);
        }
        $is_reference = $tokens->try_consume_token_type(Lexer::TOKEN_REFERENCE);
        $is_variadic = $tokens->try_consume_token_type(Lexer::TOKEN_VARIADIC);
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        if ($type !== null) {
            return new Ast\Php_Doc\Param_Tag_Value_Node($type, $is_variadic, $parameter_name, $description, $is_reference);
        }
        return new Ast\Php_Doc\Typeless_Param_Tag_Value_Node($is_variadic, $parameter_name, $description, $is_reference);
    }
    private function parse_param_immediately_invoked_callable_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Param_Immediately_Invoked_Callable_Tag_Value_Node
    {
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Param_Immediately_Invoked_Callable_Tag_Value_Node($parameter_name, $description);
    }
    private function parse_param_later_invoked_callable_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Param_Later_Invoked_Callable_Tag_Value_Node
    {
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Param_Later_Invoked_Callable_Tag_Value_Node($parameter_name, $description);
    }
    private function parse_param_closure_this_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Param_Closure_This_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Param_Closure_This_Tag_Value_Node($type, $parameter_name, $description);
    }
    private function parse_pure_unless_callable_is_impure_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Pure_Unless_Callable_Is_Impure_Tag_Value_Node
    {
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Pure_Unless_Callable_Is_Impure_Tag_Value_Node($parameter_name, $description);
    }
    private function parse_var_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Var_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $variable_name = $this->parse_optional_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, $variable_name === '');
        return new Ast\Php_Doc\Var_Tag_Value_Node($type, $variable_name, $description);
    }
    private function parse_return_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Return_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Return_Tag_Value_Node($type, $description);
    }
    private function parse_throws_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Throws_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Throws_Tag_Value_Node($type, $description);
    }
    private function parse_mixin_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Mixin_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Mixin_Tag_Value_Node($type, $description);
    }
    private function parse_require_extends_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Require_Extends_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Require_Extends_Tag_Value_Node($type, $description);
    }
    private function parse_require_implements_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Require_Implements_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Require_Implements_Tag_Value_Node($type, $description);
    }
    private function parse_sealed_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Sealed_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Sealed_Tag_Value_Node($type, $description);
    }
    private function parse_deprecated_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Deprecated_Tag_Value_Node
    {
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Deprecated_Tag_Value_Node($description);
    }
    private function parse_property_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Property_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Property_Tag_Value_Node($type, $parameter_name, $description);
    }
    private function parse_method_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Method_Tag_Value_Node
    {
        $static_keyword_or_return_type_or_method_name = $this->type_parser->parse($tokens);
        if ($static_keyword_or_return_type_or_method_name instanceof Ast\Type\Identifier_Type_Node && $static_keyword_or_return_type_or_method_name->name === 'static') {
            $is_static = true;
            $return_type_or_method_name = $this->type_parser->parse($tokens);
        } else {
            $is_static = false;
            $return_type_or_method_name = $static_keyword_or_return_type_or_method_name;
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $return_type = $return_type_or_method_name;
            $method_name = $tokens->current_token_value();
            $tokens->next();
        } elseif ($return_type_or_method_name instanceof Ast\Type\Identifier_Type_Node) {
            $return_type = $is_static ? $static_keyword_or_return_type_or_method_name : null;
            $method_name = $return_type_or_method_name->name;
            $is_static = false;
        } else {
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
            // will throw exception
            exit;
        }
        $template_types = [];
        if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
            do {
                $start_line = $tokens->current_token_line();
                $start_index = $tokens->current_token_index();
                $template_types[] = $this->enrich_with_attributes($tokens, $this->type_parser->parse_template_tag_value($tokens), $start_line, $start_index);
            } while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA));
            $tokens->consume_token_type(Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        }
        $parameters = [];
        $tokens->consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES);
        if (!$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PARENTHESES)) {
            $parameters[] = $this->parse_method_tag_value_parameter($tokens);
            while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA)) {
                $parameters[] = $this->parse_method_tag_value_parameter($tokens);
            }
        }
        $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Method_Tag_Value_Node($is_static, $return_type, $method_name, $parameters, $description, $template_types);
    }
    private function parse_method_tag_value_parameter(Token_Iterator $tokens): Ast\Php_Doc\Method_Tag_Value_Parameter_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        switch ($tokens->current_token_type()) {
            case Lexer::TOKEN_IDENTIFIER:
            case Lexer::TOKEN_OPEN_PARENTHESES:
            case Lexer::TOKEN_NULLABLE:
                $parameter_type = $this->type_parser->parse($tokens);
                break;
            default:
                $parameter_type = null;
        }
        $is_reference = $tokens->try_consume_token_type(Lexer::TOKEN_REFERENCE);
        $is_variadic = $tokens->try_consume_token_type(Lexer::TOKEN_VARIADIC);
        $parameter_name = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_VARIABLE);
        if ($tokens->try_consume_token_type(Lexer::TOKEN_EQUAL)) {
            $default_value = $this->constant_expr_parser->parse($tokens);
        } else {
            $default_value = null;
        }
        return $this->enrich_with_attributes($tokens, new Ast\Php_Doc\Method_Tag_Value_Parameter_Node($parameter_type, $is_reference, $is_variadic, $parameter_name, $default_value), $start_line, $start_index);
    }
    private function parse_extends_tag_value(string $tag_name, Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Tag_Value_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $base_type = new Identifier_Type_Node($tokens->current_token_value());
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        $type = $this->type_parser->parse_generic($tokens, $this->type_parser->enrich_with_attributes($tokens, $base_type, $start_line, $start_index));
        $description = $this->parse_optional_description($tokens, true);
        switch ($tag_name) {
            case '@extends':
                return new Ast\Php_Doc\Extends_Tag_Value_Node($type, $description);
            case '@implements':
                return new Ast\Php_Doc\Implements_Tag_Value_Node($type, $description);
            case '@use':
                return new Ast\Php_Doc\Uses_Tag_Value_Node($type, $description);
        }
        throw new Should_Not_Happen_Exception();
    }
    private function parse_type_alias_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Type_Alias_Tag_Value_Node
    {
        $alias = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        // support phan-type/psalm-type syntax
        $tokens->try_consume_token_type(Lexer::TOKEN_EQUAL);
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        try {
            $type = $this->type_parser->parse($tokens);
            if (!$tokens->is_current_token_type(Lexer::TOKEN_CLOSE_PHPDOC)) {
                if (!$tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL)) {
                    throw new Parser_Exception($tokens->current_token_value(), $tokens->current_token_type(), $tokens->current_token_offset(), Lexer::TOKEN_PHPDOC_EOL, null, $tokens->current_token_line());
                }
            }
            return new Ast\Php_Doc\Type_Alias_Tag_Value_Node($alias, $type);
        } catch (Parser_Exception $e) {
            $this->parse_optional_description($tokens, false);
            return new Ast\Php_Doc\Type_Alias_Tag_Value_Node($alias, $this->enrich_with_attributes($tokens, new Ast\Type\Invalid_Type_Node($e), $start_line, $start_index));
        }
    }
    private function parse_type_alias_import_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Type_Alias_Import_Tag_Value_Node
    {
        $imported_alias = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        $tokens->consume_token_value(Lexer::TOKEN_IDENTIFIER, 'from');
        $identifier_start_line = $tokens->current_token_line();
        $identifier_start_index = $tokens->current_token_index();
        $imported_from = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        $imported_from_type = $this->enrich_with_attributes($tokens, new Identifier_Type_Node($imported_from), $identifier_start_line, $identifier_start_index);
        $imported_as = null;
        if ($tokens->try_consume_token_value('as')) {
            $imported_as = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
        }
        return new Ast\Php_Doc\Type_Alias_Import_Tag_Value_Node($imported_alias, $imported_from_type, $imported_as);
    }
    /**
     * @return Ast\PhpDoc\AssertTagValueNode|Ast\PhpDoc\AssertTagPropertyValueNode|Ast\PhpDoc\AssertTagMethodValueNode
     */
    private function parse_assert_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Php_Doc_Tag_Value_Node
    {
        $is_negated = $tokens->try_consume_token_type(Lexer::TOKEN_NEGATED);
        $is_equality = $tokens->try_consume_token_type(Lexer::TOKEN_EQUAL);
        $type = $this->type_parser->parse($tokens);
        $parameter = $this->parse_assert_parameter($tokens);
        $description = $this->parse_optional_description($tokens, false);
        if (array_key_exists('method', $parameter)) {
            return new Ast\Php_Doc\Assert_Tag_Method_Value_Node($type, $parameter['parameter'], $parameter['method'], $is_negated, $description, $is_equality);
        }
        if (array_key_exists('property', $parameter)) {
            return new Ast\Php_Doc\Assert_Tag_Property_Value_Node($type, $parameter['parameter'], $parameter['property'], $is_negated, $description, $is_equality);
        }
        return new Ast\Php_Doc\Assert_Tag_Value_Node($type, $parameter['parameter'], $is_negated, $description, $is_equality);
    }
    /**
     * @return array{parameter: string}|array{parameter: string, property: string}|array{parameter: string, method: string}
     */
    private function parse_assert_parameter(Token_Iterator $tokens): array
    {
        if ($tokens->is_current_token_type(Lexer::TOKEN_THIS_VARIABLE)) {
            $parameter = '$this';
            $tokens->next();
        } else {
            $parameter = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_VARIABLE);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_ARROW)) {
            $tokens->consume_token_type(Lexer::TOKEN_ARROW);
            $property_or_method = $tokens->current_token_value();
            $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
            if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES)) {
                $tokens->consume_token_type(Lexer::TOKEN_CLOSE_PARENTHESES);
                return ['parameter' => $parameter, 'method' => $property_or_method];
            }
            return ['parameter' => $parameter, 'property' => $property_or_method];
        }
        return ['parameter' => $parameter];
    }
    private function parse_self_out_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Self_Out_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $description = $this->parse_optional_description($tokens, true);
        return new Ast\Php_Doc\Self_Out_Tag_Value_Node($type, $description);
    }
    private function parse_param_out_tag_value(Token_Iterator $tokens): Ast\Php_Doc\Param_Out_Tag_Value_Node
    {
        $type = $this->type_parser->parse($tokens);
        $parameter_name = $this->parse_required_variable_name($tokens);
        $description = $this->parse_optional_description($tokens, false);
        return new Ast\Php_Doc\Param_Out_Tag_Value_Node($type, $parameter_name, $description);
    }
    private function parse_optional_variable_name(Token_Iterator $tokens): string
    {
        if ($tokens->is_current_token_type(Lexer::TOKEN_VARIABLE)) {
            $parameter_name = $tokens->current_token_value();
            $tokens->next();
        } elseif ($tokens->is_current_token_type(Lexer::TOKEN_THIS_VARIABLE)) {
            $parameter_name = '$this';
            $tokens->next();
        } else {
            $parameter_name = '';
        }
        return $parameter_name;
    }
    private function parse_required_variable_name(Token_Iterator $tokens): string
    {
        $parameter_name = $tokens->current_token_value();
        $tokens->consume_token_type(Lexer::TOKEN_VARIABLE);
        return $parameter_name;
    }
    /**
     * @param bool $limitStartToken true should be used when the description immediately follows a parsed type
     */
    private function parse_optional_description(Token_Iterator $tokens, bool $limit_start_token): string
    {
        if ($limit_start_token) {
            foreach (self::DISALLOWED_DESCRIPTION_START_TOKENS as $disallowed_start_token) {
                if (!$tokens->is_current_token_type($disallowed_start_token)) {
                    continue;
                }
                $tokens->consume_token_type(Lexer::TOKEN_OTHER);
                // will throw exception
            }
            if (!$tokens->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL, Lexer::TOKEN_CLOSE_PHPDOC, Lexer::TOKEN_END) && !$tokens->is_preceded_by_horizontal_whitespace()) {
                $tokens->consume_token_type(Lexer::TOKEN_HORIZONTAL_WS);
                // will throw exception
            }
        }
        return $this->parse_text($tokens)->text;
    }
}