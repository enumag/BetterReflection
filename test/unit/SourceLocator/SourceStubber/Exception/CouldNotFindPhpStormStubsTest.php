<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\SourceStubber\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\Exception\CouldNotFindPhpStormStubs;

/**
 * @covers \PHPStan\BetterReflection\SourceLocator\SourceStubber\Exception\CouldNotFindPhpStormStubs
 */
class CouldNotFindPhpStormStubsTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = CouldNotFindPhpStormStubs::create();

        self::assertInstanceOf(CouldNotFindPhpStormStubs::class, $exception);
        self::assertSame('Could not find PhpStorm stubs', $exception->getMessage());
    }
}
