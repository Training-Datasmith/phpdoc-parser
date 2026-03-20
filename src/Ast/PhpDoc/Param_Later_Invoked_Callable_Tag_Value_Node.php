<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function trim;
class Param_Later_Invoked_Callable_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public string $parameter_name;
    /** @var string (may be empty) */
    public string $description;
    public function __construct(string $parameter_name, string $description)
    {
        $this->parameter_name = $parameter_name;
        $this->description = $description;
    }
    public function __toString(): string
    {
        return trim("{$this->parameter_name} {$this->description}");
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['parameterName'], $properties['description']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}