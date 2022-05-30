<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Identifier\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Identifier\Exception\InvalidIdentifierName;

/**
 * @covers \PHPStan\BetterReflection\Identifier\Exception\InvalidIdentifierName
 */
class InvalidIdentifierNameTest extends TestCase
{
    public function testFromInvalidName(): void
    {
        $exception = InvalidIdentifierName::fromInvalidName('!@#$%^&*()');

        self::assertInstanceOf(InvalidIdentifierName::class, $exception);
        self::assertSame('Invalid identifier name "!@#$%^&*()"', $exception->getMessage());
    }
}
