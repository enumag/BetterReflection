<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\NodeCompiler;

use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\NodeCompiler\CompiledValue;

/**
 * @covers \PHPStan\BetterReflection\NodeCompiler\CompiledValue
 */
class CompiledValueTest extends TestCase
{
    public function testValuesHappyPath(): void
    {
        $compiledValue = new CompiledValue('value', 'constantName');

        self::assertSame('value', $compiledValue->value);
        self::assertSame('constantName', $compiledValue->constantName);
    }
}
