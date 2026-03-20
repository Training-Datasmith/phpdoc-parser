<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Php_Doc_Text_Node implements Php_Doc_Child_Node
{
    use Node_Attributes;
    public string $text;
    public function __construct(string $text)
    {
        $this->text = $text;
    }
    public function __toString(): string
    {
        return $this->text;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['text']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}