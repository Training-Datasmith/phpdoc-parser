<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use function count;
use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
class Method_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public bool $is_static;
    public ?Type_Node $return_type = null;
    public string $method_name;
    /** @var TemplateTagValueNode[] */
    public array $template_types;
    /** @var MethodTagValueParameterNode[] */
    public array $parameters;
    /** @var string (may be empty) */
    public string $description;
    /**
     * @param MethodTagValueParameterNode[] $parameters
     * @param TemplateTagValueNode[] $templateTypes
     */
    public function __construct(bool $is_static, ?Type_Node $return_type, string $method_name, array $parameters, string $description, array $template_types)
    {
        $this->is_static = $is_static;
        $this->return_type = $return_type;
        $this->method_name = $method_name;
        $this->parameters = $parameters;
        $this->description = $description;
        $this->template_types = $template_types;
    }
    public function __toString(): string
    {
        $static = $this->is_static ? 'static ' : '';
        $return_type = $this->return_type !== null ? "{$this->return_type} " : '';
        $parameters = implode(', ', $this->parameters);
        $description = $this->description !== '' ? " {$this->description}" : '';
        $template_types = count($this->template_types) > 0 ? '<' . implode(', ', $this->template_types) . '>' : '';
        return "{$static}{$return_type}{$this->method_name}{$template_types}({$parameters}){$description}";
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['isStatic'], $properties['returnType'], $properties['methodName'], $properties['parameters'], $properties['description'], $properties['templateTypes']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}