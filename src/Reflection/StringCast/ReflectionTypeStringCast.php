<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection\StringCast;

use PHPStan\BetterReflection\Reflection\ReflectionIntersectionType;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType;
use PHPStan\BetterReflection\Reflection\ReflectionUnionType;

use function array_filter;
use function array_values;
use function count;

/**
 * @internal
 */
final class ReflectionTypeStringCast
{
    /**
     * @param \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType|\PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType $type
     */
    public static function toString($type): string
    {
        if ($type instanceof ReflectionUnionType) {
            // php-src has this weird behavior where a union type composed of a single type `T`
            // together with `null` means that a `ReflectionNamedType` for `?T` is produced,
            // rather than `T|null`. This is done to keep BC compatibility with PHP 7.1 (which
            // introduced nullable types), but at reflection level, this is mostly a nuisance.
            // In order to keep parity with core `Reflector#__toString()` behavior, we stashed
            // this weird behavior in here.
            $nonNullTypes = array_values(array_filter($type->getTypes(), static function (ReflectionNamedType $type) : bool {
                return $type->getName() !== 'null';
            }));

            if ($type->allowsNull() && count($nonNullTypes) === 1) {
                return '?' . $nonNullTypes[0]->__toString();
            }
        }
        return $type->__toString();
    }
}
