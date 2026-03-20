<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Parser;

use function assert;
use Exception;
use function json_encode;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use Php_Stan\Php_Doc_Parser\Lexer\Lexer;
use function sprintf;
class Parser_Exception extends Exception
{
    private string $current_token_value;
    private int $current_token_type;
    private int $current_offset;
    private int $expected_token_type;
    private ?string $expected_token_value;
    private ?int $current_token_line;
    public function __construct(string $current_token_value, int $current_token_type, int $current_offset, int $expected_token_type, ?string $expected_token_value, ?int $current_token_line)
    {
        $this->current_token_value = $current_token_value;
        $this->current_token_type = $current_token_type;
        $this->current_offset = $current_offset;
        $this->expected_token_type = $expected_token_type;
        $this->expected_token_value = $expected_token_value;
        $this->current_token_line = $current_token_line;
        parent::__construct(sprintf('Unexpected token %s, expected %s%s at offset %d%s', $this->format_value($current_token_value), Lexer::TOKEN_LABELS[$expected_token_type], $expected_token_value !== null ? sprintf(' (%s)', $this->format_value($expected_token_value)) : '', $current_offset, $current_token_line === null ? '' : sprintf(' on line %d', $current_token_line)));
    }
    public function get_current_token_value(): string
    {
        return $this->current_token_value;
    }
    public function get_current_token_type(): int
    {
        return $this->current_token_type;
    }
    public function get_current_offset(): int
    {
        return $this->current_offset;
    }
    public function get_expected_token_type(): int
    {
        return $this->expected_token_type;
    }
    public function get_expected_token_value(): ?string
    {
        return $this->expected_token_value;
    }
    public function get_current_token_line(): ?int
    {
        return $this->current_token_line;
    }
    private function format_value(string $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        assert($json !== false);
        return $json;
    }
}