<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection\Adapter;

use PhpParser\Node\Expr;
use ReflectionEnumBackedCase as CoreReflectionEnumBackedCase;
use PHPStan\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;
use PHPStan\BetterReflection\Reflection\ReflectionEnumCase as BetterReflectionEnumCase;
use UnitEnum;
use ValueError;

use function array_map;

final class ReflectionEnumBackedCase extends CoreReflectionEnumBackedCase
{
    public function __construct(private BetterReflectionEnumCase $betterReflectionEnumCase)
    {
    }

    /**
     * Get the name of the reflection (e.g. if this is a ReflectionClass this
     * will be the class name).
     */
    public function getName(): string
    {
        return $this->betterReflectionEnumCase->getName();
    }

    public function getValue(): UnitEnum
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function getModifiers(): int
    {
        return self::IS_PUBLIC;
    }

    public function getDeclaringClass(): ReflectionClass
    {
        return new ReflectionClass($this->betterReflectionEnumCase->getDeclaringClass());
    }

    public function getDocComment(): string|false
    {
        return $this->betterReflectionEnumCase->getDocComment() ?: false;
    }

    public function __toString(): string
    {
        return $this->betterReflectionEnumCase->__toString();
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
            $attributes = $this->betterReflectionEnumCase->getAttributesByInstance($name);
        } elseif ($name !== null) {
            $attributes = $this->betterReflectionEnumCase->getAttributesByName($name);
        } else {
            $attributes = $this->betterReflectionEnumCase->getAttributes();
        }

        return array_map(static fn (BetterReflectionAttribute $betterReflectionAttribute): ReflectionAttribute|FakeReflectionAttribute => ReflectionAttributeFactory::create($betterReflectionAttribute), $attributes);
    }

    public function isFinal(): bool
    {
        return true;
    }

    public function isEnumCase(): bool
    {
        return true;
    }

    public function getEnum(): ReflectionEnum
    {
        return new ReflectionEnum($this->betterReflectionEnumCase->getDeclaringEnum());
    }

    /**
     * @deprecated Use getValueExpr()
     */
    public function getBackingValue(): int|string
    {
        return $this->betterReflectionEnumCase->getValue();
    }

    public function getValueExpr(): Expr
    {
        return $this->betterReflectionEnumCase->getValueExpr();
    }
}
