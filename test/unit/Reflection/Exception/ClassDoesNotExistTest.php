<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Exception\ClassDoesNotExist;
use PHPStan\BetterReflection\Reflection\Reflection;

/**
 * @covers \PHPStan\BetterReflection\Reflection\Exception\ClassDoesNotExist
 */
class ClassDoesNotExistTest extends TestCase
{
    public function testForDifferentReflectionType(): void
    {
        $reflection = $this->createMock(Reflection::class);

        $reflection
            ->expects(self::any())
            ->method('getName')
            ->willReturn('potato');

        $exception = ClassDoesNotExist::forDifferentReflectionType($reflection);

        self::assertInstanceOf(ClassDoesNotExist::class, $exception);
        self::assertSame('The reflected type "potato" is not a class', $exception->getMessage());
    }
}
