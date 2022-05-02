<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use Attribute;
use LogicException;
use PhpParser\Node;
use Roave\BetterReflection\NodeCompiler\CompileNodeToValue;
use Roave\BetterReflection\NodeCompiler\CompilerContext;
use Roave\BetterReflection\Reflection\StringCast\ReflectionAttributeStringCast;
use Roave\BetterReflection\Reflector\Reflector;

class ReflectionAttribute
{
    private string $name;

    /** @var array<int|string, Node\Expr> */
    private array $argumentExprs;

    /** @var array<int|string, mixed> */
    private ?array $cachedArguments = null;

    public function __construct(
        private Reflector $reflector,
        Node\Attribute $node,
        private ReflectionClass|ReflectionMethod|ReflectionFunction|ReflectionClassConstant|ReflectionEnumCase|ReflectionProperty|ReflectionParameter $owner,
        private bool $isRepeated,
    ) {
        $this->name = $node->name->toString();

        $argumentExprs = [];
        foreach ($node->args as $argNo => $arg) {
            $argumentExprs[$arg->name?->toString() ?? $argNo] = $arg->value;
        }
        $this->argumentExprs = $argumentExprs;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClass(): ReflectionClass
    {
        return $this->reflector->reflectClass($this->getName());
    }

    /**
     * @deprecated Use getArgumentsExpressions()
     * @return array<int|string, mixed>
     */
    public function getArguments(): array
    {
        if ($this->cachedArguments !== null) {
            return $this->cachedArguments;
        }

        $arguments = [];

        $compiler = new CompileNodeToValue();
        $context  = new CompilerContext($this->reflector, $this->owner);

        foreach ($this->argumentExprs as $key => $expr) {
            /** @psalm-suppress MixedAssignment */
            $arguments[$key] = $compiler->__invoke($expr, $context)->value;
        }

        return $this->cachedArguments = $arguments;
    }

    /** @return array<int|string, Node\Expr> */
    public function getArgumentsExpressions(): array
    {
        return $this->argumentExprs;
    }

    public function getTarget(): int
    {
        return match (true) {
            $this->owner instanceof ReflectionClass => Attribute::TARGET_CLASS,
            $this->owner instanceof ReflectionFunction => Attribute::TARGET_FUNCTION,
            $this->owner instanceof ReflectionMethod => Attribute::TARGET_METHOD,
            $this->owner instanceof ReflectionProperty => Attribute::TARGET_PROPERTY,
            $this->owner instanceof ReflectionClassConstant => Attribute::TARGET_CLASS_CONSTANT,
            $this->owner instanceof ReflectionEnumCase => Attribute::TARGET_CLASS_CONSTANT,
            $this->owner instanceof ReflectionParameter => Attribute::TARGET_PARAMETER,
            default => throw new LogicException('unknown owner'), // @phpstan-ignore-line
        };
    }

    public function isRepeated(): bool
    {
        return $this->isRepeated;
    }

    public function __toString(): string
    {
        return ReflectionAttributeStringCast::toString($this);
    }
}
