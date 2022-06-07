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
    /**
     * @var string
     */
    private $name;

    /** @var array<int|string, Node\Expr> */
    private $argumentExprs;

    /** @var array<int|string, mixed> */
    private $cachedArguments;
    /**
     * @var \Roave\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionClassConstant|\Roave\BetterReflection\Reflection\ReflectionEnumCase|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty
     */
    private $owner;
    /**
     * @var bool
     */
    private $isRepeated;
    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionClassConstant|\Roave\BetterReflection\Reflection\ReflectionEnumCase|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty $owner
     */
    public function __construct(Reflector $reflector, Node\Attribute $node, $owner, bool $isRepeated)
    {
        $this->reflector = $reflector;
        $this->owner = $owner;
        $this->isRepeated = $isRepeated;
        $this->name = $node->name->toString();
        $argumentExprs = [];
        foreach ($node->args as $argNo => $arg) {
            $argumentExprs[(($argName = $arg->name) ? $argName->toString() : null) ?? $argNo] = $arg->value;
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
        switch (true) {
            case $this->owner instanceof ReflectionClass:
                return Attribute::TARGET_CLASS;
            case $this->owner instanceof ReflectionFunction:
                return Attribute::TARGET_FUNCTION;
            case $this->owner instanceof ReflectionMethod:
                return Attribute::TARGET_METHOD;
            case $this->owner instanceof ReflectionProperty:
                return Attribute::TARGET_PROPERTY;
            case $this->owner instanceof ReflectionClassConstant:
                return Attribute::TARGET_CLASS_CONSTANT;
            case $this->owner instanceof ReflectionEnumCase:
                return Attribute::TARGET_CLASS_CONSTANT;
            case $this->owner instanceof ReflectionParameter:
                return Attribute::TARGET_PARAMETER;
            default:
                throw new LogicException('unknown owner');
        }
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
