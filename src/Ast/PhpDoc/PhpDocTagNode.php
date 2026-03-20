<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine\Doctrine_Tag_Value_Node;
use function trim;
class Php_Doc_Tag_Node implements Php_Doc_Child_Node
{
    use Node_Attributes;
    public string $name;
    public Php_Doc_Tag_Value_Node $value;
    public function __construct(string $name, Php_Doc_Tag_Value_Node $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
    public function __toString(): string
    {
        if ($this->value instanceof Doctrine_Tag_Value_Node) {
            return (string) $this->value;
        }
        return trim("{$this->name} {$this->value}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['name'], $properties['value']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}