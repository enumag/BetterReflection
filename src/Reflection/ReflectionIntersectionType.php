<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use Roave\BetterReflection\Reflector\Reflector;

use function array_map;
use function assert;
use function implode;

class ReflectionIntersectionType extends ReflectionType
{
    /** @var non-empty-list<ReflectionNamedType> */
    private $types;

    /** @internal
     * @param \Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionEnum|\Roave\BetterReflection\Reflection\ReflectionProperty $owner */
    public function __construct(Reflector $reflector, $owner, IntersectionType $type)
    {
        /** @var non-empty-list<ReflectionNamedType> $types */
        $types = array_map(static function ($type) use ($reflector, $owner): ReflectionNamedType {
            $type = ReflectionType::createFromNode($reflector, $owner, $type);
            assert($type instanceof ReflectionNamedType);

            return $type;
        }, $type->types);
        $this->types = $types;
    }

    /** @internal
     * @param \Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionEnum|\Roave\BetterReflection\Reflection\ReflectionProperty $owner
     * @return $this */
    public function withOwner($owner)
    {
        $clone = clone $this;

        foreach ($clone->types as $typeNo => $innerType) {
            $clone->types[$typeNo] = $innerType->withOwner($owner);
        }

        return $clone;
    }

    /** @return non-empty-list<ReflectionNamedType> */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function allowsNull(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        // @infection-ignore-all UnwrapArrayMap: It works without array_map() as well but this is less magical
        return implode('&', array_map(static function (ReflectionNamedType $type) : string {
            return $type->__toString();
        }, $this->types));
    }
}
