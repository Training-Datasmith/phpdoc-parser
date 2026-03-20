# Architecture: phpdoc-parser

## Purpose

A strict PHPDoc comment parser that produces a typed AST. Used by PHPStan, Psalm, and other static analysis tools to extract and interpret type information from PHPDoc annotations.

## Directory Structure

```
src/
  Lexer/
    Lexer.php                — Tokenizes a PHPDoc comment string into a token stream
  Parser/
    Php_Doc_Parser.php       — Top-level parser: builds Php_Doc_Node from token stream
    Type_Parser.php          — Parses type expressions (union, intersection, generic, callable, etc.)
    Const_Expr_Parser.php    — Parses constant expressions inside types (arrays, scalars, enums)
    Token_Iterator.php       — Cursor over the token stream with lookahead
    Parser_Exception.php     — Thrown when the token stream does not match expected grammar
    String_Unescaper.php     — Unescapes string literals in PHPDoc values
  Ast/
    Php_Doc_Node.php         — Root AST node for a full PHPDoc block
    Php_Doc_Tag_Node.php     — A single @tag node
    PhpDoc/                  — Tag-specific value nodes (@param, @return, @var, @throws, etc.)
    Type/                    — Type nodes (union, intersection, array, generic, nullable, etc.)
    ConstExpr/               — Constant expression nodes (integers, strings, arrays, null, etc.)
    Node_Traverser.php       — Walks the AST; calls visitor enter/leave methods
    Node_Visitor.php         — Interface for AST visitors
    Abstract_Node_Visitor.php — Default no-op visitor base class
  Printer/
    Printer.php              — Converts an AST back to a PHPDoc string
    Differ.php               — Minimal diff for reformatting (used by Printer)
  Parser_Config.php          — Configuration: whether to require braces, etc.
```

## Key Design Decisions

- **Immutable AST nodes**: All AST node properties are public and set in constructors; nodes are not mutated after construction
- **Printer round-trip**: The `Printer` can serialize any AST node back to its string form, enabling in-place PHPDoc rewriting in tools like Rector
- **Doctrine annotation support**: Dedicated `Doctrine_*` nodes handle `@Annotation("value")` syntax used by Doctrine ORM
- **Node attributes**: Nodes implement `Node_Attributes` to carry start/end offset information for IDE and refactoring tools
- **Visitor pattern**: `Node_Traverser` + `Node_Visitor` enable AST transformations without modifying node classes

## Extension Points

- Implement `Node_Visitor` to inspect or rewrite AST nodes
- Use `Printer` to serialize a modified AST back to a PHPDoc string
- Pass `Parser_Config` to control parser strictness

## Dependency Flow

```
PHPDoc string
  → Lexer::tokenize()             → token array
  → TokenIterator (cursor)
  → PhpDocParser::parse()
      → TypeParser (for type expressions)
      → ConstExprParser (for constant values)
  → PhpDocNode (AST)
  → [optional] NodeTraverser + NodeVisitor  (transform)
  → [optional] Printer::print()             (back to string)
```
