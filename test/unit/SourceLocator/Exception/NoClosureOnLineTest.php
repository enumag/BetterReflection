<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\SourceLocator\Exception\NoClosureOnLine;

/**
 * @covers \PHPStan\BetterReflection\SourceLocator\Exception\NoClosureOnLine
 */
class NoClosureOnLineTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = NoClosureOnLine::create('foo.php', 123);

        self::assertInstanceOf(NoClosureOnLine::class, $exception);
        self::assertSame('No closure found on line 123 in foo.php', $exception->getMessage());
    }
}
