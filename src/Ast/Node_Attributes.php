<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast;

use function array_key_exists;
trait Node_Attributes
{
    /** @var array<string, mixed> */
    private array $attributes = [];
    /**
     * @param mixed $value
     */
    public function set_attribute(string $key, $value): void
    {
        if ($value === null) {
            unset($this->attributes[$key]);
            return;
        }
        $this->attributes[$key] = $value;
    }
    public function has_attribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }
    /**
     * @return mixed
     */
    public function get_attribute(string $key)
    {
        if ($this->has_attribute($key)) {
            return $this->attributes[$key];
        }
        return null;
    }
}