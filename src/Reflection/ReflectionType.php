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
    /** @internal
     * @param \PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionProperty $owner
     * @param \PhpParser\Node\Identifier|\PhpParser\Node\Name|\PhpParser\Node\NullableType|\PhpParser\Node\UnionType|\PhpParser\Node\IntersectionType $type
     * @return \PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType|\PHPStan\BetterReflection\Reflection\ReflectionIntersectionType */
    public static function createFromNode(Reflector $reflector, $owner, $type, bool $allowsNull = false)
    {
        if ($type instanceof NullableType) {
            $type       = $type->type;
            $allowsNull = true;
        }
        if ($type instanceof Identifier || $type instanceof Name) {
            if (
                $type->toLowerString() === 'null'
                || $type->toLowerString() === 'mixed'
                || ! $allowsNull
            ) {
                return new ReflectionNamedType($reflector, $owner, $type);
            }

            return new ReflectionUnionType($reflector, $owner, new UnionType([$type, new Identifier('null')]));
        }
        if ($type instanceof IntersectionType) {
            return new ReflectionIntersectionType($reflector, $owner, $type);
        }
        if (! $allowsNull) {
            return new ReflectionUnionType($reflector, $owner, $type);
        }
        foreach ($type->types as $innerUnionType) {
            /** @psalm-suppress RedundantConditionGivenDocblockType https://github.com/nikic/PHP-Parser/pull/889 */
            if (
                /** @phpstan-ignore-next-line https://github.com/nikic/PHP-Parser/pull/889 */
                ($innerUnionType instanceof Identifier || $innerUnionType instanceof Name)
                && $innerUnionType->toLowerString() === 'null'
            ) {
                return new ReflectionUnionType($reflector, $owner, $type);
            }
        }
        $types   = $type->types;
        $types[] = new Identifier('null');
        return new ReflectionUnionType($reflector, $owner, new UnionType($types));
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
