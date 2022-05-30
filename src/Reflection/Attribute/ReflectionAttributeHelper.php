<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Attribute;

use PhpParser\Node\AttributeGroup;
use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionClassConstant;
use Roave\BetterReflection\Reflection\ReflectionEnumCase;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionParameter;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\Reflector;

use function array_filter;
use function array_values;
use function count;

/**
 * @internal
 */
class ReflectionAttributeHelper
{
    /**
     * @param list<AttributeGroup> $attrGroups
     * @return list<ReflectionAttribute>
     * @param \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionClassConstant|\Roave\BetterReflection\Reflection\ReflectionEnumCase|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty $reflection
     */
    public static function createAttributes(Reflector $reflector, $reflection, array $attrGroups)
    {
        $repeated = [];
        foreach ($attrGroups as $attributesGroupNode) {
            foreach ($attributesGroupNode->attrs as $attributeNode) {
                $repeated[$attributeNode->name->toLowerString()][] = $attributeNode;
            }
        }
        $attributes = [];
        foreach ($attrGroups as $attributesGroupNode) {
            foreach ($attributesGroupNode->attrs as $attributeNode) {
                $attributes[] = new ReflectionAttribute($reflector, $attributeNode, $reflection, count($repeated[$attributeNode->name->toLowerString()]) > 1);
            }
        }
        return $attributes;
    }

    /**
     * @param list<ReflectionAttribute> $attributes
     *
     * @return list<ReflectionAttribute>
     */
    public static function filterAttributesByName(array $attributes, string $name): array
    {
        return array_values(array_filter($attributes, static function (ReflectionAttribute $attribute) use ($name) : bool {
            return $attribute->getName() === $name;
        }));
    }

    /**
     * @param list<ReflectionAttribute> $attributes
     * @param class-string              $className
     *
     * @return list<ReflectionAttribute>
     */
    public static function filterAttributesByInstance(array $attributes, string $className): array
    {
        return array_values(array_filter($attributes, static function (ReflectionAttribute $attribute) use ($className): bool {
            $class = $attribute->getClass();

            return $class->getName() === $className || $class->isSubclassOf($className) || $class->implementsInterface($className);
        }));
    }
}
