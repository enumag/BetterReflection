<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PHPStan\BetterReflection\Reflector\Reflector;

use function array_map;
use function array_values;
use function assert;
use function implode;

class ReflectionIntersectionType extends ReflectionType
{
    /** @var list<ReflectionNamedType> */
    private $types;

    /**
     * @param \PHPStan\BetterReflection\Reflection\ReflectionEnum|\PHPStan\BetterReflection\Reflection\ReflectionFunction|\PHPStan\BetterReflection\Reflection\ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionParameter|\PHPStan\BetterReflection\Reflection\ReflectionProperty $owner
     */
    public function __construct(Reflector $reflector, $owner, IntersectionType $type)
    {
        parent::__construct($reflector, $owner);
        $this->types = array_values(array_map(static function ($type) use ($reflector, $owner): ReflectionNamedType {
            $type = ReflectionType::createFromNode($reflector, $owner, $type);
            assert($type instanceof ReflectionNamedType);

            return $type;
        }, $type->types));
    }

    /**
     * @return list<ReflectionNamedType>
     */
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
        return implode('&', array_map(static function (ReflectionNamedType $type) : string {
            return $type->__toString();
        }, $this->types));
    }
}
