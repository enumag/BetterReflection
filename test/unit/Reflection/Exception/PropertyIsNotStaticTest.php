<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Exception\PropertyIsNotStatic;

#[CoversClass(PropertyIsNotStatic::class)]
class PropertyIsNotStaticTest extends TestCase
{
    public function testFromName(): void
    {
        $exception = PropertyIsNotStatic::fromName('boo');

        self::assertInstanceOf(PropertyIsNotStatic::class, $exception);
        self::assertSame('Property "boo" is not static', $exception->getMessage());
    }
}
