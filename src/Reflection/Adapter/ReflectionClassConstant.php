<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Adapter;

use PhpParser\Node\Expr;
use ReflectionClassConstant as CoreReflectionClassConstant;
use ReturnTypeWillChange;
use Roave\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClassConstant as BetterReflectionClassConstant;
use Roave\BetterReflection\Reflection\ReflectionEnumCase as BetterReflectionEnumCase;
use ValueError;

use function array_map;

final class ReflectionClassConstant extends CoreReflectionClassConstant
{
    /**
     * @var BetterReflectionClassConstant|BetterReflectionEnumCase
     */
    private $betterClassConstantOrEnumCase;
    /**
     * @param BetterReflectionClassConstant|BetterReflectionEnumCase $betterClassConstantOrEnumCase
     */
    public function __construct($betterClassConstantOrEnumCase)
    {
        $this->betterClassConstantOrEnumCase = $betterClassConstantOrEnumCase;
    }

    /**
     * Get the name of the reflection (e.g. if this is a ReflectionClass this
     * will be the class name).
     */
    public function getName(): string
    {
        return $this->betterClassConstantOrEnumCase->getName();
    }

    #[ReturnTypeWillChange]
    /**
     * @deprecated Use getValueExpr()
     */
    public function getValue()
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            throw new Exception\NotImplemented('Not implemented');
        }

        return $this->betterClassConstantOrEnumCase->getValue();
    }

    public function getValueExpr(): Expr
    {
        return $this->betterClassConstantOrEnumCase->getValueExpr();
    }

    /**
     * Constant is public
     */
    public function isPublic(): bool
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            return true;
        }

        return $this->betterClassConstantOrEnumCase->isPublic();
    }

    /**
     * Constant is private
     */
    public function isPrivate(): bool
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            return false;
        }

        return $this->betterClassConstantOrEnumCase->isPrivate();
    }

    /**
     * Constant is protected
     */
    public function isProtected(): bool
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            return false;
        }

        return $this->betterClassConstantOrEnumCase->isProtected();
    }

    /**
     * Returns a bitfield of the access modifiers for this constant
     */
    public function getModifiers(): int
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            return CoreReflectionClassConstant::IS_PUBLIC;
        }

        return $this->betterClassConstantOrEnumCase->getModifiers();
    }

    /**
     * Get the declaring class
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return new ReflectionClass($this->betterClassConstantOrEnumCase->getDeclaringClass());
    }

    /**
     * Returns the doc comment for this constant
     *
     * @return string|false
     */
    #[ReturnTypeWillChange]
    public function getDocComment()
    {
        return $this->betterClassConstantOrEnumCase->getDocComment() ?: false;
    }

    /**
     * To string
     *
     * @link https://php.net/manual/en/reflector.tostring.php
     */
    public function __toString(): string
    {
        return $this->betterClassConstantOrEnumCase->__toString();
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
            $attributes = $this->betterClassConstantOrEnumCase->getAttributesByInstance($name);
        } elseif ($name !== null) {
            $attributes = $this->betterClassConstantOrEnumCase->getAttributesByName($name);
        } else {
            $attributes = $this->betterClassConstantOrEnumCase->getAttributes();
        }

        return array_map(static function (BetterReflectionAttribute $betterReflectionAttribute) {
            return ReflectionAttributeFactory::create($betterReflectionAttribute);
        }, $attributes);
    }

    public function isFinal(): bool
    {
        if ($this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase) {
            return true;
        }

        return $this->betterClassConstantOrEnumCase->isFinal();
    }

    public function isEnumCase(): bool
    {
        return $this->betterClassConstantOrEnumCase instanceof BetterReflectionEnumCase;
    }
}
