<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser;

class Parser_Config
{
    public bool $use_lines_attributes;
    public bool $use_index_attributes;
    public bool $use_comments_attributes;
    /**
     * @param array{lines?: bool, indexes?: bool, comments?: bool} $usedAttributes
     */
    public function __construct(array $used_attributes)
    {
        $this->use_lines_attributes = $used_attributes['lines'] ?? false;
        $this->use_index_attributes = $used_attributes['indexes'] ?? false;
        $this->use_comments_attributes = $used_attributes['comments'] ?? false;
    }
}