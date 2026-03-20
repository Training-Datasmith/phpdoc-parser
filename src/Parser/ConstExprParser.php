<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Parser;

use Php_Stan\Php_Doc_Parser\Ast;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use Php_Stan\Php_Doc_Parser\Parser_Config;
use function str_replace;
use function strtolower;
class Const_Expr_Parser
{
    private Parser_Config $config;
    private bool $parse_doctrine_strings;
    public function __construct(Parser_Config $config)
    {
        $this->config = $config;
        $this->parse_doctrine_strings = false;
    }
    /**
     * @internal
     */
    public function to_doctrine(): self
    {
        $self = new self($this->config);
        $self->parse_doctrine_strings = true;
        return $self;
    }
    public function parse(Token_Iterator $tokens): Ast\Const_Expr\Const_Expr_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        if ($tokens->is_current_token_type(Lexer::TOKEN_FLOAT)) {
            $value = $tokens->current_token_value();
            $tokens->next();
            return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_Float_Node(str_replace('_', '', $value)), $start_line, $start_index);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_INTEGER)) {
            $value = $tokens->current_token_value();
            $tokens->next();
            return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_Integer_Node(str_replace('_', '', $value)), $start_line, $start_index);
        }
        if ($this->parse_doctrine_strings && $tokens->is_current_token_type(Lexer::TOKEN_DOCTRINE_ANNOTATION_STRING)) {
            $value = $tokens->current_token_value();
            $tokens->next();
            return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Doctrine_Const_Expr_String_Node(Ast\Const_Expr\Doctrine_Const_Expr_String_Node::unescape($value)), $start_line, $start_index);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_SINGLE_QUOTED_STRING, Lexer::TOKEN_DOUBLE_QUOTED_STRING)) {
            if ($this->parse_doctrine_strings) {
                if ($tokens->is_current_token_type(Lexer::TOKEN_SINGLE_QUOTED_STRING)) {
                    throw new Parser_Exception($tokens->current_token_value(), $tokens->current_token_type(), $tokens->current_token_offset(), Lexer::TOKEN_DOUBLE_QUOTED_STRING, null, $tokens->current_token_line());
                }
                $value = $tokens->current_token_value();
                $tokens->next();
                return $this->enrich_with_attributes($tokens, $this->parse_doctrine_string($value, $tokens), $start_line, $start_index);
            }
            $value = String_Unescaper::unescape_string($tokens->current_token_value());
            $type = $tokens->current_token_type();
            $tokens->next();
            return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_String_Node($value, $type === Lexer::TOKEN_SINGLE_QUOTED_STRING ? Ast\Const_Expr\Const_Expr_String_Node::SINGLE_QUOTED : Ast\Const_Expr\Const_Expr_String_Node::DOUBLE_QUOTED), $start_line, $start_index);
        }
        if ($tokens->is_current_token_type(Lexer::TOKEN_IDENTIFIER)) {
            $identifier = $tokens->current_token_value();
            $tokens->next();
            switch (strtolower($identifier)) {
                case 'true':
                    return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_True_Node(), $start_line, $start_index);
                case 'false':
                    return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_False_Node(), $start_line, $start_index);
                case 'null':
                    return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_Null_Node(), $start_line, $start_index);
                case 'array':
                    $tokens->consume_token_type(Lexer::TOKEN_OPEN_PARENTHESES);
                    return $this->parse_array($tokens, Lexer::TOKEN_CLOSE_PARENTHESES, $start_index);
            }
            if ($tokens->try_consume_token_type(Lexer::TOKEN_DOUBLE_COLON)) {
                $class_constant_name = '';
                $last_type = null;
                while (true) {
                    if ($last_type !== Lexer::TOKEN_IDENTIFIER && $tokens->current_token_type() === Lexer::TOKEN_IDENTIFIER) {
                        $class_constant_name .= $tokens->current_token_value();
                        $tokens->consume_token_type(Lexer::TOKEN_IDENTIFIER);
                        $last_type = Lexer::TOKEN_IDENTIFIER;
                        continue;
                    }
                    if ($last_type !== Lexer::TOKEN_WILDCARD && $tokens->try_consume_token_type(Lexer::TOKEN_WILDCARD)) {
                        $class_constant_name .= '*';
                        $last_type = Lexer::TOKEN_WILDCARD;
                        if ($tokens->get_skipped_horizontal_white_space_if_any() !== '') {
                            break;
                        }
                        continue;
                    }
                    if ($last_type === null) {
                        // trigger parse error if nothing valid was consumed
                        $tokens->consume_token_type(Lexer::TOKEN_WILDCARD);
                    }
                    break;
                }
                return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Fetch_Node($identifier, $class_constant_name), $start_line, $start_index);
            }
            return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Fetch_Node('', $identifier), $start_line, $start_index);
        }
        if ($tokens->try_consume_token_type(Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
            return $this->parse_array($tokens, Lexer::TOKEN_CLOSE_SQUARE_BRACKET, $start_index);
        }
        throw new Parser_Exception($tokens->current_token_value(), $tokens->current_token_type(), $tokens->current_token_offset(), Lexer::TOKEN_IDENTIFIER, null, $tokens->current_token_line());
    }
    private function parse_array(Token_Iterator $tokens, int $end_token, int $start_index): Ast\Const_Expr\Const_Expr_Array_Node
    {
        $items = [];
        $start_line = $tokens->current_token_line();
        if (!$tokens->try_consume_token_type($end_token)) {
            do {
                $items[] = $this->parse_array_item($tokens);
            } while ($tokens->try_consume_token_type(Lexer::TOKEN_COMMA) && !$tokens->is_current_token_type($end_token));
            $tokens->consume_token_type($end_token);
        }
        return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_Array_Node($items), $start_line, $start_index);
    }
    /**
     * This method is supposed to be called with TokenIterator after reading TOKEN_DOUBLE_QUOTED_STRING and shifting
     * to the next token.
     */
    public function parse_doctrine_string(string $text, Token_Iterator $tokens): Ast\Const_Expr\Doctrine_Const_Expr_String_Node
    {
        // Because of how Lexer works, a valid Doctrine string
        // can consist of a sequence of TOKEN_DOUBLE_QUOTED_STRING and TOKEN_DOCTRINE_ANNOTATION_STRING
        while ($tokens->is_current_token_type(Lexer::TOKEN_DOUBLE_QUOTED_STRING, Lexer::TOKEN_DOCTRINE_ANNOTATION_STRING)) {
            $text .= $tokens->current_token_value();
            $tokens->next();
        }
        return new Ast\Const_Expr\Doctrine_Const_Expr_String_Node(Ast\Const_Expr\Doctrine_Const_Expr_String_Node::unescape($text));
    }
    private function parse_array_item(Token_Iterator $tokens): Ast\Const_Expr\Const_Expr_Array_Item_Node
    {
        $start_line = $tokens->current_token_line();
        $start_index = $tokens->current_token_index();
        $expr = $this->parse($tokens);
        if ($tokens->try_consume_token_type(Lexer::TOKEN_DOUBLE_ARROW)) {
            $key = $expr;
            $value = $this->parse($tokens);
        } else {
            $key = null;
            $value = $expr;
        }
        return $this->enrich_with_attributes($tokens, new Ast\Const_Expr\Const_Expr_Array_Item_Node($key, $value), $start_line, $start_index);
    }
    /**
     * @template T of Ast\ConstExpr\ConstExprNode
     * @param T $node
     * @return T
     */
    private function enrich_with_attributes(Token_Iterator $tokens, Ast\Const_Expr\Const_Expr_Node $node, int $start_line, int $start_index): Ast\Const_Expr\Const_Expr_Node
    {
        if ($this->config->use_lines_attributes) {
            $node->set_attribute(Ast\Attribute::START_LINE, $start_line);
            $node->set_attribute(Ast\Attribute::END_LINE, $tokens->current_token_line());
        }
        if ($this->config->use_index_attributes) {
            $node->set_attribute(Ast\Attribute::START_INDEX, $start_index);
            $node->set_attribute(Ast\Attribute::END_INDEX, $tokens->end_index_of_last_relevant_token());
        }
        return $node;
    }
}