<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection;

use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPStan\BetterReflection\Reflector\Reflector;

abstract class ReflectionType
{
    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    protected $reflector;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionProperty
     */
    protected $owner;
    /**
     * @param \PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionProperty $owner
     */
    protected function __construct(Reflector $reflector, $owner)
    {
        $this->reflector = $reflector;
        $this->owner = $owner;
    }
    /**
     * @internal
     * @param \PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionProperty $owner
     * @param \PhpParser\Node\Identifier|\PhpParser\Node\IntersectionType|\PhpParser\Node\Name|\PhpParser\Node\NullableType|\PhpParser\Node\UnionType $type
     * @return \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType|\PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType
     */
    public static function createFromNode(Reflector $reflector, $owner, $type, bool $allowsNull = false)
    {
        if ($type instanceof NullableType) {
            $type       = $type->type;
            $allowsNull = true;
        }
        if ($type instanceof Identifier || $type instanceof Name) {
            if ($allowsNull) {
                return new ReflectionUnionType($reflector, $owner, new UnionType([$type, new Identifier('null')]));
            }

            return new ReflectionNamedType($reflector, $owner, $type);
        }
        if ($type instanceof IntersectionType) {
            return new ReflectionIntersectionType($reflector, $owner, $type);
        }
        if (! $allowsNull) {
            return new ReflectionUnionType($reflector, $owner, $type);
        }
        $hasNull = false;
        foreach ($type->types as $innerUnionType) {
            if (! $innerUnionType instanceof Identifier || $innerUnionType->toLowerString() !== 'null') {
                continue;
            }

            $hasNull = true;
        }
        if ($hasNull) {
            return new ReflectionUnionType($reflector, $owner, $type);
        }
        $types   = $type->types;
        $types[] = new Identifier('null');
        return new ReflectionUnionType($reflector, $owner, new UnionType($types));
    }
    /**
     * @internal
     * @return \PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionProperty
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Does the type allow null?
     */
    abstract public function allowsNull(): bool;

    /**
     * Convert this string type to a string
     */
    abstract public function __toString(): string;
}
