<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Parser;

use function array_pop;
use function assert;
use function count;
use function in_array;
use LogicException;
use Php_Stan\Php_Doc_Parser\Ast\Comment;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use function strlen;
use function substr;
class Token_Iterator
{
    /** @var list<array{string, int, int}> */
    private array $tokens;
    private int $index;
    /** @var list<Comment> */
    private array $comments = [];
    /** @var list<array{int, list<Comment>}> */
    private array $save_points = [];
    /** @var list<int> */
    private array $skipped_token_types = [Lexer::TOKEN_HORIZONTAL_WS];
    private ?string $newline = null;
    /**
     * @param list<array{string, int, int}> $tokens
     */
    public function __construct(array $tokens, int $index = 0)
    {
        $this->tokens = $tokens;
        $this->index = $index;
        $this->skip_irrelevant_tokens();
    }
    /**
     * @return list<array{string, int, int}>
     */
    public function get_tokens(): array
    {
        return $this->tokens;
    }
    public function get_content_between(int $start_pos, int $end_pos): string
    {
        if ($start_pos < 0 || $end_pos > count($this->tokens)) {
            throw new LogicException();
        }
        $content = '';
        for ($i = $start_pos; $i < $end_pos; $i++) {
            $content .= $this->tokens[$i][Lexer::VALUE_OFFSET];
        }
        return $content;
    }
    public function get_token_count(): int
    {
        return count($this->tokens);
    }
    public function current_token_value(): string
    {
        return $this->tokens[$this->index][Lexer::VALUE_OFFSET];
    }
    public function current_token_type(): int
    {
        return $this->tokens[$this->index][Lexer::TYPE_OFFSET];
    }
    public function current_token_offset(): int
    {
        $offset = 0;
        for ($i = 0; $i < $this->index; $i++) {
            $offset += strlen($this->tokens[$i][Lexer::VALUE_OFFSET]);
        }
        return $offset;
    }
    public function current_token_line(): int
    {
        return $this->tokens[$this->index][Lexer::LINE_OFFSET];
    }
    public function current_token_index(): int
    {
        return $this->index;
    }
    public function end_index_of_last_relevant_token(): int
    {
        $end_index = $this->current_token_index();
        $end_index--;
        while (in_array($this->tokens[$end_index][Lexer::TYPE_OFFSET], $this->skipped_token_types, true)) {
            if (!isset($this->tokens[$end_index - 1])) {
                break;
            }
            $end_index--;
        }
        return $end_index;
    }
    public function is_current_token_value(string $token_value): bool
    {
        return $this->tokens[$this->index][Lexer::VALUE_OFFSET] === $token_value;
    }
    public function is_current_token_type(int ...$token_type): bool
    {
        return in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $token_type, true);
    }
    public function is_preceded_by_horizontal_whitespace(): bool
    {
        return ($this->tokens[$this->index - 1][Lexer::TYPE_OFFSET] ?? -1) === Lexer::TOKEN_HORIZONTAL_WS;
    }
    /**
     * @throws ParserException
     */
    public function consume_token_type(int $token_type): void
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $token_type) {
            $this->throw_error($token_type);
        }
        if ($token_type === Lexer::TOKEN_PHPDOC_EOL) {
            if ($this->newline === null) {
                $this->detect_newline();
            }
        }
        $this->next();
    }
    /**
     * @throws ParserException
     */
    public function consume_token_value(int $token_type, string $token_value): void
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $token_type || $this->tokens[$this->index][Lexer::VALUE_OFFSET] !== $token_value) {
            $this->throw_error($token_type, $token_value);
        }
        $this->next();
    }
    /** @phpstan-impure */
    public function try_consume_token_value(string $token_value): bool
    {
        if ($this->tokens[$this->index][Lexer::VALUE_OFFSET] !== $token_value) {
            return false;
        }
        $this->next();
        return true;
    }
    /**
     * @return list<Comment>
     */
    public function flush_comments(): array
    {
        $res = $this->comments;
        $this->comments = [];
        return $res;
    }
    /** @phpstan-impure */
    public function try_consume_token_type(int $token_type): bool
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $token_type) {
            return false;
        }
        if ($token_type === Lexer::TOKEN_PHPDOC_EOL) {
            if ($this->newline === null) {
                $this->detect_newline();
            }
        }
        $this->next();
        return true;
    }
    /**
     * @deprecated Use skipNewLineTokensAndConsumeComments instead (when parsing a type)
     */
    public function skip_new_line_tokens(): void
    {
        if (!$this->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL)) {
            return;
        }
        do {
            $found_new_line = $this->try_consume_token_type(Lexer::TOKEN_PHPDOC_EOL);
        } while ($found_new_line === true);
    }
    public function skip_new_line_tokens_and_consume_comments(): void
    {
        if ($this->current_token_type() === Lexer::TOKEN_COMMENT) {
            $this->comments[] = new Comment($this->current_token_value(), $this->current_token_line(), $this->current_token_index());
            $this->next();
        }
        if (!$this->is_current_token_type(Lexer::TOKEN_PHPDOC_EOL)) {
            return;
        }
        do {
            $found_new_line = $this->try_consume_token_type(Lexer::TOKEN_PHPDOC_EOL);
            if ($this->current_token_type() !== Lexer::TOKEN_COMMENT) {
                continue;
            }
            $this->comments[] = new Comment($this->current_token_value(), $this->current_token_line(), $this->current_token_index());
            $this->next();
        } while ($found_new_line === true);
    }
    private function detect_newline(): void
    {
        $value = $this->current_token_value();
        if (substr($value, 0, 2) === "\r\n") {
            $this->newline = "\r\n";
        } elseif (substr($value, 0, 1) === "\n") {
            $this->newline = "\n";
        }
    }
    public function get_skipped_horizontal_white_space_if_any(): string
    {
        if ($this->index > 0 && $this->tokens[$this->index - 1][Lexer::TYPE_OFFSET] === Lexer::TOKEN_HORIZONTAL_WS) {
            return $this->tokens[$this->index - 1][Lexer::VALUE_OFFSET];
        }
        return '';
    }
    /** @phpstan-impure */
    public function join_until(int ...$token_type): string
    {
        $s = '';
        while (!in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $token_type, true)) {
            $s .= $this->tokens[$this->index++][Lexer::VALUE_OFFSET];
        }
        return $s;
    }
    public function next(): void
    {
        $this->index++;
        $this->skip_irrelevant_tokens();
    }
    private function skip_irrelevant_tokens(): void
    {
        if (!isset($this->tokens[$this->index])) {
            return;
        }
        while (in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $this->skipped_token_types, true)) {
            if (!isset($this->tokens[$this->index + 1])) {
                break;
            }
            $this->index++;
        }
    }
    public function add_end_of_line_to_skipped_tokens(): void
    {
        $this->skipped_token_types = [Lexer::TOKEN_HORIZONTAL_WS, Lexer::TOKEN_PHPDOC_EOL];
    }
    public function remove_end_of_line_from_skipped_tokens(): void
    {
        $this->skipped_token_types = [Lexer::TOKEN_HORIZONTAL_WS];
    }
    /** @phpstan-impure */
    public function forward_to_the_end(): void
    {
        $last_token = count($this->tokens) - 1;
        $this->index = $last_token;
    }
    public function push_save_point(): void
    {
        $this->save_points[] = [$this->index, $this->comments];
    }
    public function drop_save_point(): void
    {
        array_pop($this->save_points);
    }
    public function rollback(): void
    {
        $savepoint = array_pop($this->save_points);
        assert($savepoint !== null);
        [$this->index, $this->comments] = $savepoint;
    }
    /**
     * @throws ParserException
     */
    private function throw_error(int $expected_token_type, ?string $expected_token_value = null): void
    {
        throw new Parser_Exception($this->current_token_value(), $this->current_token_type(), $this->current_token_offset(), $expected_token_type, $expected_token_value, $this->current_token_line());
    }
    /**
     * Check whether the position is directly preceded by a certain token type.
     *
     * During this check TOKEN_HORIZONTAL_WS and TOKEN_PHPDOC_EOL are skipped
     */
    public function has_token_immediately_before(int $pos, int $expected_token_type): bool
    {
        $tokens = $this->tokens;
        $pos--;
        for (; $pos >= 0; $pos--) {
            $token = $tokens[$pos];
            $type = $token[Lexer::TYPE_OFFSET];
            if ($type === $expected_token_type) {
                return true;
            }
            if (!in_array($type, [Lexer::TOKEN_HORIZONTAL_WS, Lexer::TOKEN_PHPDOC_EOL], true)) {
                break;
            }
        }
        return false;
    }
    /**
     * Check whether the position is directly followed by a certain token type.
     *
     * During this check TOKEN_HORIZONTAL_WS and TOKEN_PHPDOC_EOL are skipped
     */
    public function has_token_immediately_after(int $pos, int $expected_token_type): bool
    {
        $tokens = $this->tokens;
        $pos++;
        for ($c = count($tokens); $pos < $c; $pos++) {
            $token = $tokens[$pos];
            $type = $token[Lexer::TYPE_OFFSET];
            if ($type === $expected_token_type) {
                return true;
            }
            if (!in_array($type, [Lexer::TOKEN_HORIZONTAL_WS, Lexer::TOKEN_PHPDOC_EOL], true)) {
                break;
            }
        }
        return false;
    }
    public function get_detected_newline(): ?string
    {
        return $this->newline;
    }
    /**
     * Whether the given position is immediately surrounded by parenthesis.
     */
    public function has_parentheses(int $start_pos, int $end_pos): bool
    {
        return $this->has_token_immediately_before($start_pos, Lexer::TOKEN_OPEN_PARENTHESES) && $this->has_token_immediately_after($end_pos, Lexer::TOKEN_CLOSE_PARENTHESES);
    }
}