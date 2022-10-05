<?php

namespace Roave\BetterReflectionTest\Fixture;

use PHPStan\BetterReflection\TypesFinder\FindReturnType;

class TestClassForPhpParserPrinterTest
{
    public function foo() : FindReturnType
    {
        return new FindReturnType();
    }
}
