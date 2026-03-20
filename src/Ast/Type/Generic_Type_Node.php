<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use function sprintf;
class Generic_Type_Node implements Type_Node
{
    use Node_Attributes;
    public const VARIANCE_INVARIANT = 'invariant';
    public const VARIANCE_COVARIANT = 'covariant';
    public const VARIANCE_CONTRAVARIANT = 'contravariant';
    public const VARIANCE_BIVARIANT = 'bivariant';
    public Identifier_Type_Node $type;
    /** @var TypeNode[] */
    public array $generic_types;
    /** @var (self::VARIANCE_*)[] */
    public array $variances;
    /**
     * @param TypeNode[] $genericTypes
     * @param (self::VARIANCE_*)[] $variances
     */
    public function __construct(Identifier_Type_Node $type, array $generic_types, array $variances = [])
    {
        $this->type = $type;
        $this->generic_types = $generic_types;
        $this->variances = $variances;
    }
    public function __toString(): string
    {
        $generic_types = [];
        foreach ($this->generic_types as $index => $type) {
            $variance = $this->variances[$index] ?? self::VARIANCE_INVARIANT;
            if ($variance === self::VARIANCE_INVARIANT) {
                $generic_types[] = (string) $type;
            } elseif ($variance === self::VARIANCE_BIVARIANT) {
                $generic_types[] = '*';
            } else {
                $generic_types[] = sprintf('%s %s', $variance, $type);
            }
        }
        return $this->type . '<' . implode(', ', $generic_types) . '>';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['type'], $properties['genericTypes'], $properties['variances'] ?? []);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}