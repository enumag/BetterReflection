<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\SourceStubber;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\StubData;

/**
 * @covers \PHPStan\BetterReflection\SourceLocator\SourceStubber\StubData
 */
class StubDataTest extends TestCase
{
    public function testGetters(): void
    {
        $stub          = '<?php';
        $extensionName = 'Core';
        $stubData      = new StubData($stub, $extensionName, null);

        self::assertSame($stub, $stubData->getStub());
        self::assertSame($extensionName, $stubData->getExtensionName());
    }
}
