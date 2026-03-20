<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class This_Type_Node implements Type_Node
{
    use Node_Attributes;
    public function __toString(): string
    {
        return '$this';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self();
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}