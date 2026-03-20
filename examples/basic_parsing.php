<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;

// --- Setup: create the parser components ---
$lexer = new Lexer();
$const_expr_parser = new ConstExprParser();
$type_parser = new TypeParser($const_expr_parser);
$phpdoc_parser = new PhpDocParser($type_parser, $const_expr_parser);

// --- Example 1: Parse a basic PHPDoc block ---
$phpdoc_string = '/**
 * Calculates the sum of two integers.
 *
 * @param int $a The first operand.
 * @param int $b The second operand.
 * @return int The sum of $a and $b.
 * @throws \InvalidArgumentException If either argument is negative.
 */';

$tokens = new TokenIterator($lexer->tokenize($phpdoc_string));
$phpdoc_node = $phpdoc_parser->parse($tokens);

echo "Tags found:\n";
foreach ($phpdoc_node->getTags() as $tag) {
    echo "  " . $tag->name . ": " . $tag->value . "\n";
}
echo "\n";

// --- Example 2: Access param and return tags ---
$param_tags = $phpdoc_node->getParamTagValues();
foreach ($param_tags as $param) {
    echo "@param {$param->type} {$param->parameterName} — {$param->description}\n";
}

$return_tags = $phpdoc_node->getReturnTagValues();
foreach ($return_tags as $ret) {
    echo "@return {$ret->type} — {$ret->description}\n";
}
echo "\n";

// --- Example 3: Parse complex union types ---
$complex_phpdoc = '/**
 * @param array<string, int|float> $scores Score map.
 * @param ?callable(string): bool  $filter Optional filter callback.
 * @return list<non-empty-string>
 */';

$tokens2 = new TokenIterator($lexer->tokenize($complex_phpdoc));
$complex_node = $phpdoc_parser->parse($tokens2);

foreach ($complex_node->getParamTagValues() as $param) {
    echo "Param type: {$param->type}\n";
}
foreach ($complex_node->getReturnTagValues() as $ret) {
    echo "Return type: {$ret->type}\n";
}
