<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Adapter;

use PhpParser\Node\Expr;
use ReflectionClass as CoreReflectionClass;
use ReflectionFunctionAbstract as CoreReflectionFunctionAbstract;
use ReflectionParameter as CoreReflectionParameter;
use ReturnTypeWillChange;
use Roave\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionMethod as BetterReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionParameter as BetterReflectionParameter;
use ValueError;

use function array_map;

/**
 * @psalm-suppress MissingImmutableAnnotation
 */
final class ReflectionParameter extends CoreReflectionParameter
{
    /**
     * @var BetterReflectionParameter
     */
    private $betterReflectionParameter;
    public function __construct(BetterReflectionParameter $betterReflectionParameter)
    {
        $this->betterReflectionParameter = $betterReflectionParameter;
    }

    public function __toString(): string
    {
        return $this->betterReflectionParameter->__toString();
    }

    public function getName(): string
    {
        return $this->betterReflectionParameter->getName();
    }

    public function isPassedByReference(): bool
    {
        return $this->betterReflectionParameter->isPassedByReference();
    }

    public function canBePassedByValue(): bool
    {
        return $this->betterReflectionParameter->canBePassedByValue();
    }

    /**
     * @return ReflectionFunction|ReflectionMethod
     */
    public function getDeclaringFunction(): CoreReflectionFunctionAbstract
    {
        $function = $this->betterReflectionParameter->getDeclaringFunction();

        if ($function instanceof BetterReflectionMethod) {
            return new ReflectionMethod($function);
        }

        return new ReflectionFunction($function);
    }

    /**
     * @return ReflectionClass|null
     */
    public function getDeclaringClass(): ?CoreReflectionClass
    {
        $declaringClass = $this->betterReflectionParameter->getDeclaringClass();

        if ($declaringClass === null) {
            return null;
        }

        return new ReflectionClass($declaringClass);
    }

    /**
     * @return ReflectionClass|null
     */
    public function getClass(): ?CoreReflectionClass
    {
        $class = $this->betterReflectionParameter->getClass();

        if ($class === null) {
            return null;
        }

        return new ReflectionClass($class);
    }

    public function isArray(): bool
    {
        return $this->betterReflectionParameter->isArray();
    }

    public function isCallable(): bool
    {
        return $this->betterReflectionParameter->isCallable();
    }

    public function allowsNull(): bool
    {
        return $this->betterReflectionParameter->allowsNull();
    }

    public function getPosition(): int
    {
        return $this->betterReflectionParameter->getPosition();
    }

    public function isOptional(): bool
    {
        return $this->betterReflectionParameter->isOptional();
    }

    public function isVariadic(): bool
    {
        return $this->betterReflectionParameter->isVariadic();
    }

    public function isDefaultValueAvailable(): bool
    {
        return $this->betterReflectionParameter->isDefaultValueAvailable();
    }

    /**
     * @deprecated Use getDefaultValueExpr()
     */
    #[ReturnTypeWillChange]
    public function getDefaultValue()
    {
        return $this->betterReflectionParameter->getDefaultValue();
    }

    public function getDefaultValueExpr(): Expr
    {
        return $this->betterReflectionParameter->getDefaultValueExpr();
    }

    public function isDefaultValueConstant(): bool
    {
        return $this->betterReflectionParameter->isDefaultValueConstant();
    }

    public function getDefaultValueConstantName(): string
    {
        return $this->betterReflectionParameter->getDefaultValueConstantName();
    }

    public function hasType(): bool
    {
        return $this->betterReflectionParameter->hasType();
    }

    /**
     * @return ReflectionUnionType|ReflectionNamedType|ReflectionIntersectionType|null
     */
    public function getType(): ?\ReflectionType
    {
        return ReflectionType::fromTypeOrNull($this->betterReflectionParameter->getType());
    }

    public function isPromoted(): bool
    {
        return $this->betterReflectionParameter->isPromoted();
    }

    /**
     * @param class-string|null $name
     *
     * @return list<ReflectionAttribute|FakeReflectionAttribute>
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if ($flags !== 0 && $flags !== ReflectionAttribute::IS_INSTANCEOF) {
            throw new ValueError('Argument #2 ($flags) must be a valid attribute filter flag');
        }

        if ($name !== null && $flags !== 0) {
            $attributes = $this->betterReflectionParameter->getAttributesByInstance($name);
        } elseif ($name !== null) {
            $attributes = $this->betterReflectionParameter->getAttributesByName($name);
        } else {
            $attributes = $this->betterReflectionParameter->getAttributes();
        }

        return array_map(static function (BetterReflectionAttribute $betterReflectionAttribute) {
            return ReflectionAttributeFactory::create($betterReflectionAttribute);
        }, $attributes);
    }
}
