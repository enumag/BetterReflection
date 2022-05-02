<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use LogicException;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\EnumCase;
use Roave\BetterReflection\NodeCompiler\CompiledValue;
use Roave\BetterReflection\NodeCompiler\CompileNodeToValue;
use Roave\BetterReflection\NodeCompiler\CompilerContext;
use Roave\BetterReflection\Reflection\Annotation\AnnotationHelper;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\Reflection\StringCast\ReflectionEnumCaseStringCast;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\Util\CalculateReflectionColumn;
use Roave\BetterReflection\Util\Exception\NoNodePosition;
use Roave\BetterReflection\Util\GetLastDocComment;

use function assert;
use function is_int;
use function is_string;

class ReflectionEnumCase
{
    private ?CompiledValue $compiledValue = null;

    /** @var list<ReflectionAttribute> */
    private array $attributes;

    private string $name;

    private ?Expr $expr;

    private int $startLine;

    private int $endLine;

    private int $startColumn;

    private int $endColumn;

    private string $docComment;

    private function __construct(
        private Reflector $reflector,
        EnumCase $node,
        private ReflectionEnum $enum,
    ) {
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
        $this->name = $node->name->toString();
        $this->expr = $node->expr;
        $this->startLine = $node->getStartLine();
        $this->endLine = $node->getEndLine();

        try {
            $this->startColumn = CalculateReflectionColumn::getStartColumn($this->enum->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->startColumn = -1;
        }

        try {
            $this->endColumn = CalculateReflectionColumn::getEndColumn($this->enum->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->endColumn = -1;
        }

        $this->docComment = GetLastDocComment::forNode($node);
    }

    /**
     * @internal
     */
    public static function createFromNode(
        Reflector $reflector,
        EnumCase $node,
        ReflectionEnum $enum,
    ): self {
        return new self($reflector, $node, $enum);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @deprecated Use getValueExpr() */
    public function getValue(): string|int
    {
        $value = $this->getCompiledValue()->value;
        assert(is_string($value) || is_int($value));

        return $value;
    }

    public function getValueExpr(): Expr
    {
        if ($this->expr === null) {
            throw new LogicException('This enum case does not have a value');
        }

        return $this->expr;
    }

    /**
     * @throws LogicException
     */
    private function getCompiledValue(): CompiledValue
    {
        if ($this->expr === null) {
            throw new LogicException('This enum case does not have a value');
        }

        if ($this->compiledValue === null) {
            $this->compiledValue = (new CompileNodeToValue())->__invoke(
                $this->expr,
                new CompilerContext($this->reflector, $this),
            );
        }

        return $this->compiledValue;
    }

    public function getStartLine(): int
    {
        return $this->startLine;
    }

    public function getEndLine(): int
    {
        return $this->endLine;
    }

    public function getStartColumn(): int
    {
        return $this->startColumn;
    }

    public function getEndColumn(): int
    {
        return $this->endColumn;
    }

    public function getDeclaringEnum(): ReflectionEnum
    {
        return $this->enum;
    }

    public function getDeclaringClass(): ReflectionClass
    {
        return $this->enum;
    }

    public function getDocComment(): string
    {
        return $this->docComment;
    }

    public function isDeprecated(): bool
    {
        return AnnotationHelper::isDeprecated($this->getDocComment());
    }

    /**
     * @return list<ReflectionAttribute>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<ReflectionAttribute>
     */
    public function getAttributesByName(string $name): array
    {
        return ReflectionAttributeHelper::filterAttributesByName($this->getAttributes(), $name);
    }

    /**
     * @param class-string $className
     *
     * @return list<ReflectionAttribute>
     */
    public function getAttributesByInstance(string $className): array
    {
        return ReflectionAttributeHelper::filterAttributesByInstance($this->getAttributes(), $className);
    }

    public function __toString(): string
    {
        return ReflectionEnumCaseStringCast::toString($this);
    }
}
