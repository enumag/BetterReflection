<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use PhpParser\Node;
use PhpParser\Node\UnionType;
use Roave\BetterReflection\Reflector\Reflector;

use function array_filter;
use function array_map;
use function array_values;
use function implode;

class ReflectionUnionType extends ReflectionType
{
    /** @var list<ReflectionNamedType> */
    private $types;

    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionEnum|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty $owner
     */
    public function __construct(Reflector $reflector, $owner, UnionType $type)
    {
        parent::__construct($reflector, $owner);
        $this->types = array_values(array_filter(array_map(static function ($type) use ($reflector, $owner) {
            return ReflectionType::createFromNode($reflector, $owner, $type);
        }, $type->types), static function ($type) : bool {
            return $type instanceof ReflectionNamedType;
        }));
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
        foreach ($this->types as $type) {
            if ($type->getName() === 'null') {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return implode('|', array_map(static function (ReflectionType $type) : string {
            return $type->__toString();
        }, $this->types));
    }
}
