<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast;

use function trim;
class Comment
{
    public string $text;
    public int $start_line;
    public int $start_index;
    public function __construct(string $text, int $start_line = -1, int $start_index = -1)
    {
        $this->text = $text;
        $this->start_line = $start_line;
        $this->start_index = $start_index;
    }
    public function get_reformatted_text(): string
    {
        return trim($this->text);
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['text'], $properties['startLine'], $properties['startIndex']);
    }
}