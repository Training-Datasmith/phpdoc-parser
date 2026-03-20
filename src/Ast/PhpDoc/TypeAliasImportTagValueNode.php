<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Ast\Type\Identifier_Type_Node;
use function trim;
class Type_Alias_Import_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    public string $imported_alias;
    public Identifier_Type_Node $imported_from;
    public ?string $imported_as = null;
    public function __construct(string $imported_alias, Identifier_Type_Node $imported_from, ?string $imported_as)
    {
        $this->imported_alias = $imported_alias;
        $this->imported_from = $imported_from;
        $this->imported_as = $imported_as;
    }
    public function __toString(): string
    {
        return trim("{$this->imported_alias} from {$this->imported_from}" . ($this->imported_as !== null ? " as {$this->imported_as}" : ''));
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['importedAlias'], $properties['importedFrom'], $properties['importedAs']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}