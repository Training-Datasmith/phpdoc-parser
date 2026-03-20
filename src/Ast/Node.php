<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast;

interface Node
{
    public function __toString(): string;
    /**
     * @param mixed $value
     */
    public function set_attribute(string $key, $value): void;
    public function has_attribute(string $key): bool;
    /**
     * @return mixed
     */
    public function get_attribute(string $key);
}