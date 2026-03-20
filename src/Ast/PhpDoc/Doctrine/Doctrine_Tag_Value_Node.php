<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Doctrine;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Value_Node;
use function trim;
class Doctrine_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public Doctrine_Annotation $annotation;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(Doctrine_Annotation $annotation, string $description)
    {
        $this->annotation = $annotation;
        $this->description = $description;
    }
    public function __toString(): string
    {
        return trim("{$this->annotation} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['annotation'], $properties['description']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}