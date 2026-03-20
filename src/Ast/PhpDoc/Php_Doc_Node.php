<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use function array_column;
use function array_filter;
use function array_map;
use function implode;
use Php_Stan\Php_Doc_Parser\Ast\Node;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
class Php_Doc_Node implements Node
{
    use Node_Attributes;
    /** @var PhpDocChildNode[] */
    public array $children;
    /**
     * @param PhpDocChildNode[] $children
     */
    public function __construct(array $children)
    {
        $this->children = $children;
    }
    /**
     * @return PhpDocTagNode[]
     */
    public function get_tags(): array
    {
        return array_filter($this->children, static fn(Php_Doc_Child_Node $child): bool => $child instanceof Php_Doc_Tag_Node);
    }
    /**
     * @return PhpDocTagNode[]
     */
    public function get_tags_by_name(string $tag_name): array
    {
        return array_filter($this->get_tags(), static fn(Php_Doc_Tag_Node $tag): bool => $tag->name === $tag_name);
    }
    /**
     * @return VarTagValueNode[]
     */
    public function get_var_tag_values(string $tag_name = '@var'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Var_Tag_Value_Node);
    }
    /**
     * @return ParamTagValueNode[]
     */
    public function get_param_tag_values(string $tag_name = '@param'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Param_Tag_Value_Node);
    }
    /**
     * @return TypelessParamTagValueNode[]
     */
    public function get_typeless_param_tag_values(string $tag_name = '@param'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Typeless_Param_Tag_Value_Node);
    }
    /**
     * @return ParamImmediatelyInvokedCallableTagValueNode[]
     */
    public function get_param_immediately_invoked_callable_tag_values(string $tag_name = '@param-immediately-invoked-callable'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Param_Immediately_Invoked_Callable_Tag_Value_Node);
    }
    /**
     * @return ParamLaterInvokedCallableTagValueNode[]
     */
    public function get_param_later_invoked_callable_tag_values(string $tag_name = '@param-later-invoked-callable'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Param_Later_Invoked_Callable_Tag_Value_Node);
    }
    /**
     * @return ParamClosureThisTagValueNode[]
     */
    public function get_param_closure_this_tag_values(string $tag_name = '@param-closure-this'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Param_Closure_This_Tag_Value_Node);
    }
    /**
     * @return PureUnlessCallableIsImpureTagValueNode[]
     */
    public function get_pure_unless_callable_is_impure_tag_values(string $tag_name = '@pure-unless-callable-is-impure'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Pure_Unless_Callable_Is_Impure_Tag_Value_Node);
    }
    /**
     * @return TemplateTagValueNode[]
     */
    public function get_template_tag_values(string $tag_name = '@template'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Template_Tag_Value_Node);
    }
    /**
     * @return ExtendsTagValueNode[]
     */
    public function get_extends_tag_values(string $tag_name = '@extends'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Extends_Tag_Value_Node);
    }
    /**
     * @return ImplementsTagValueNode[]
     */
    public function get_implements_tag_values(string $tag_name = '@implements'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Implements_Tag_Value_Node);
    }
    /**
     * @return UsesTagValueNode[]
     */
    public function get_uses_tag_values(string $tag_name = '@use'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Uses_Tag_Value_Node);
    }
    /**
     * @return ReturnTagValueNode[]
     */
    public function get_return_tag_values(string $tag_name = '@return'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Return_Tag_Value_Node);
    }
    /**
     * @return ThrowsTagValueNode[]
     */
    public function get_throws_tag_values(string $tag_name = '@throws'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Throws_Tag_Value_Node);
    }
    /**
     * @return MixinTagValueNode[]
     */
    public function get_mixin_tag_values(string $tag_name = '@mixin'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Mixin_Tag_Value_Node);
    }
    /**
     * @return RequireExtendsTagValueNode[]
     */
    public function get_require_extends_tag_values(string $tag_name = '@phpstan-require-extends'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Require_Extends_Tag_Value_Node);
    }
    /**
     * @return RequireImplementsTagValueNode[]
     */
    public function get_require_implements_tag_values(string $tag_name = '@phpstan-require-implements'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Require_Implements_Tag_Value_Node);
    }
    /**
     * @return SealedTagValueNode[]
     */
    public function get_sealed_tag_values(string $tag_name = '@phpstan-sealed'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Sealed_Tag_Value_Node);
    }
    /**
     * @return DeprecatedTagValueNode[]
     */
    public function get_deprecated_tag_values(): array
    {
        return array_filter(array_column($this->get_tags_by_name('@deprecated'), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Deprecated_Tag_Value_Node);
    }
    /**
     * @return PropertyTagValueNode[]
     */
    public function get_property_tag_values(string $tag_name = '@property'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Property_Tag_Value_Node);
    }
    /**
     * @return PropertyTagValueNode[]
     */
    public function get_property_read_tag_values(string $tag_name = '@property-read'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Property_Tag_Value_Node);
    }
    /**
     * @return PropertyTagValueNode[]
     */
    public function get_property_write_tag_values(string $tag_name = '@property-write'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Property_Tag_Value_Node);
    }
    /**
     * @return MethodTagValueNode[]
     */
    public function get_method_tag_values(string $tag_name = '@method'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Method_Tag_Value_Node);
    }
    /**
     * @return TypeAliasTagValueNode[]
     */
    public function get_type_alias_tag_values(string $tag_name = '@phpstan-type'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Type_Alias_Tag_Value_Node);
    }
    /**
     * @return TypeAliasImportTagValueNode[]
     */
    public function get_type_alias_import_tag_values(string $tag_name = '@phpstan-import-type'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Type_Alias_Import_Tag_Value_Node);
    }
    /**
     * @return AssertTagValueNode[]
     */
    public function get_assert_tag_values(string $tag_name = '@phpstan-assert'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Assert_Tag_Value_Node);
    }
    /**
     * @return AssertTagPropertyValueNode[]
     */
    public function get_assert_property_tag_values(string $tag_name = '@phpstan-assert'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Assert_Tag_Property_Value_Node);
    }
    /**
     * @return AssertTagMethodValueNode[]
     */
    public function get_assert_method_tag_values(string $tag_name = '@phpstan-assert'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Assert_Tag_Method_Value_Node);
    }
    /**
     * @return SelfOutTagValueNode[]
     */
    public function get_self_out_type_tag_values(string $tag_name = '@phpstan-this-out'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Self_Out_Tag_Value_Node);
    }
    /**
     * @return ParamOutTagValueNode[]
     */
    public function get_param_out_type_tag_values(string $tag_name = '@param-out'): array
    {
        return array_filter(array_column($this->get_tags_by_name($tag_name), 'value'), static fn(Php_Doc_Tag_Value_Node $value): bool => $value instanceof Param_Out_Tag_Value_Node);
    }
    public function __toString(): string
    {
        $children = array_map(static function (Php_Doc_Child_Node $child): string {
            $s = (string) $child;
            return $s === '' ? '' : ' ' . $s;
        }, $this->children);
        return "/**\n *" . implode("\n *", $children) . "\n */";
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self($properties['children']);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}