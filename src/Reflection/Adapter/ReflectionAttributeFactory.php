<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Adapter;

use Roave\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;

use const PHP_VERSION_ID;

final class ReflectionAttributeFactory
{
    /**
     * @return \Roave\BetterReflection\Reflection\Adapter\FakeReflectionAttribute|\Roave\BetterReflection\Reflection\Adapter\ReflectionAttribute
     */
    public static function create(BetterReflectionAttribute $betterReflectionAttribute)
    {
        if (PHP_VERSION_ID < 70200) {
            return new FakeReflectionAttribute($betterReflectionAttribute);
        }

        if (PHP_VERSION_ID >= 80000 && PHP_VERSION_ID < 80012) {
            return new FakeReflectionAttribute($betterReflectionAttribute);
        }

        return new ReflectionAttribute($betterReflectionAttribute);
    }
}
