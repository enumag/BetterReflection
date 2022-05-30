<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\SourceLocator\Exception\EvaledClosureCannotBeLocated;

/**
 * @covers \PHPStan\BetterReflection\SourceLocator\Exception\EvaledClosureCannotBeLocated
 */
class EvaledClosureCannotBeLocatedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = EvaledClosureCannotBeLocated::create();

        self::assertInstanceOf(EvaledClosureCannotBeLocated::class, $exception);
        self::assertSame('Evaled closure cannot be located', $exception->getMessage());
    }
}
