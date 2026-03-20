<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Ast\Type;

use Php_Stan\Php_Doc_Parser\Ast\Node_Attributes;
use Php_Stan\Php_Doc_Parser\Parser\Parser_Exception;
class Invalid_Type_Node implements Type_Node
{
    use Node_Attributes;
    /** @var mixed[] */
    private array $exception_args;
    public function __construct(Parser_Exception $exception)
    {
        $this->exception_args = [$exception->get_current_token_value(), $exception->get_current_token_type(), $exception->get_current_offset(), $exception->get_expected_token_type(), $exception->get_expected_token_value(), $exception->get_current_token_line()];
    }
    public function get_exception(): Parser_Exception
    {
        return new Parser_Exception(...$this->exception_args);
    }
    public function __toString(): string
    {
        return '*Invalid type*';
    }
    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        $exception = new Parser_Exception(...$properties['exceptionArgs']);
        $instance = new self($exception);
        if (isset($properties['attributes'])) {
            foreach ($properties['attributes'] as $key => $value) {
                $instance->set_attribute($key, $value);
            }
        }
        return $instance;
    }
}