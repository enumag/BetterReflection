<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Adapter;

use ReflectionType as CoreReflectionType;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType as BetterReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionNamedType as BetterReflectionNamedType;
use Roave\BetterReflection\Reflection\ReflectionType as BetterReflectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType as BetterReflectionUnionType;

use function array_filter;
use function array_values;
use function count;

abstract class ReflectionType extends CoreReflectionType
{
    /**
     * @param BetterReflectionUnionType|BetterReflectionNamedType|BetterReflectionIntersectionType|null $betterReflectionType
     * @return \Roave\BetterReflection\Reflection\Adapter\ReflectionUnionType|\Roave\BetterReflection\Reflection\Adapter\ReflectionNamedType|\Roave\BetterReflection\Reflection\Adapter\ReflectionIntersectionType|null
     */
    public static function fromTypeOrNull($betterReflectionType)
    {
        return $betterReflectionType !== null ? self::fromType($betterReflectionType) : null;
    }

    /** @internal
     * @param BetterReflectionNamedType|BetterReflectionUnionType|BetterReflectionIntersectionType $betterReflectionType
     * @return \Roave\BetterReflection\Reflection\Adapter\ReflectionUnionType|\Roave\BetterReflection\Reflection\Adapter\ReflectionNamedType|\Roave\BetterReflection\Reflection\Adapter\ReflectionIntersectionType */
    public static function fromType($betterReflectionType)
    {
        if ($betterReflectionType instanceof BetterReflectionUnionType) {
            // php-src has this weird behavior where a union type composed of a single type `T`
            // together with `null` means that a `ReflectionNamedType` for `?T` is produced,
            // rather than `T|null`. This is done to keep BC compatibility with PHP 7.1 (which
            // introduced nullable types), but at reflection level, this is mostly a nuisance.
            // In order to keep parity with core, we stashed this weird behavior in here.
            $nonNullTypes = array_values(array_filter($betterReflectionType->getTypes(), static function (BetterReflectionType $type) : bool {
                return ! ($type instanceof BetterReflectionNamedType && $type->getName() === 'null');
            }));

            if (
                $betterReflectionType->allowsNull()
                && count($nonNullTypes) === 1
                && $nonNullTypes[0] instanceof BetterReflectionNamedType
            ) {
                return new ReflectionNamedType($nonNullTypes[0], true);
            }

            return new ReflectionUnionType($betterReflectionType);
        }

        if ($betterReflectionType instanceof BetterReflectionIntersectionType) {
            return new ReflectionIntersectionType($betterReflectionType);
        }

        return new ReflectionNamedType($betterReflectionType, $betterReflectionType->allowsNull());
    }
}
