<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Template_Tag_Value_Node;
class Callable_Type_Node implements Type_Node
{
    use Node_Attributes;
    public Identifier_Type_Node $identifier;
    /** @var TemplateTagValueNode[] */
    public array $template_types;
    /** @var CallableTypeParameterNode[] */
    public array $parameters;
    public Type_Node $return_type;
    /**
     * @param CallableTypeParameterNode[] $parameters
     * @param TemplateTagValueNode[]  $templateTypes
     */
    public function __construct(Identifier_Type_Node $identifier, array $parameters, Type_Node $return_type, array $template_types)
    {
        $this->identifier = $identifier;
        $this->parameters = $parameters;
        $this->return_type = $return_type;
        $this->template_types = $template_types;
    }
    public function __toString(): string
    {
        $return_type = $this->return_type;
        if ($return_type instanceof self) {
            $return_type = "({$return_type})";
        }
        $template = $this->template_types !== [] ? '<' . implode(', ', $this->template_types) . '>' : '';
        $parameters = implode(', ', $this->parameters);
        return "{$this->identifier}{$template}({$parameters}): {$return_type}";
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['identifier'], $properties['parameters'], $properties['returnType'], $properties['templateTypes']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}