<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Php_Doc;

use const E_USER_WARNING;
use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Parser\Parser_Exception;
use function sprintf;
use function trigger_error;
/**
 * @property ParserException $exception
 */
class Invalid_Tag_Value_Node implements Php_Doc_Tag_Value_Node
{
    use Node_Attributes;
    /** @var string (may be empty) */
    public string $value;
    /** @var mixed[] */
    private array $exception_args;
    public function __construct(string $value, Parser_Exception $exception)
    {
        $this->value = $value;
        $this->exception_args = [$exception->get_current_token_value(), $exception->get_current_token_type(), $exception->get_current_offset(), $exception->get_expected_token_type(), $exception->get_expected_token_value(), $exception->get_current_token_line()];
    }
    public function __get(string $name): ?Parser_Exception
    {
        if ($name !== 'exception') {
            trigger_error(sprintf('Undefined property: %s::$%s', self::class, $name), E_USER_WARNING);
            return null;
        }
        return new Parser_Exception(...$this->exception_args);
    }
    public function __toString(): string
    {
        return $this->value;
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $exception = new Parser_Exception(...$properties['exceptionArgs']);
        $instance = new self($properties['value'], $exception);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}