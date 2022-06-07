<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Exception\NotAnObject;

/**
 * @covers \PHPStan\BetterReflection\Reflection\Exception\NotAnObject
 */
class NotAnObjectTest extends TestCase
{
    public function testFromNonObject(): void
    {
        $exception = NotAnObject::fromNonObject(123);

        self::assertInstanceOf(NotAnObject::class, $exception);
        self::assertSame('Provided "integer" is not an object', $exception->getMessage());
    }
}
