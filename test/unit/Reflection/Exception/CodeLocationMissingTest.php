<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Exception;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Exception\CodeLocationMissing;

/** @covers \PHPStan\BetterReflection\Reflection\Exception\CodeLocationMissing */
class CodeLocationMissingTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = CodeLocationMissing::create();

        self::assertInstanceOf(CodeLocationMissing::class, $exception);
        self::assertSame('Code location is missing', $exception->getMessage());
    }
}
