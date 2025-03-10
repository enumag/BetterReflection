<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\StringCast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionFunctionStringCast;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;

#[CoversClass(ReflectionFunctionStringCast::class)]
class ReflectionFunctionStringCastTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Ast\Locator
     */
    private $astLocator;

    protected function setUp(): void
    {
        parent::setUp();

        $betterReflection = BetterReflectionSingleton::instance();

        $this->astLocator = $betterReflection->astLocator();
    }

    /** @return list<array{0: string, 1: string}> */
    public static function toStringProvider(): array
    {
        return [
            ['Roave\BetterReflectionTest\Fixture\functionWithoutParameters', "Function [ <user> function Roave\BetterReflectionTest\Fixture\\functionWithoutParameters ] {\n  @@ %s/Fixture/StringCastFunctions.php 5 - 7\n}"],
            ['Roave\BetterReflectionTest\Fixture\functionWithParameters', "Function [ <user> function Roave\BetterReflectionTest\Fixture\\functionWithParameters ] {\n  @@ %s/Fixture/StringCastFunctions.php 9 - 11\n\n  - Parameters [2] {\n    Parameter #0 [ <required> \$a ]\n    Parameter #1 [ <required> \$b ]\n  }\n}"],
            ['Roave\BetterReflectionTest\Fixture\functionWithReturnType', "Function [ <user> function Roave\BetterReflectionTest\Fixture\\functionWithReturnType ] {\n  @@ %s/Fixture/StringCastFunctions.php 13 - 15\n\n  - Parameters [0] {\n  }\n  - Return [ int ]\n}"],
        ];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(string $functionName, string $expectedString): void
    {
        $reflector          = new DefaultReflector(new SingleFileSourceLocator(__DIR__ . '/../../Fixture/StringCastFunctions.php', $this->astLocator));
        $functionReflection = $reflector->reflectFunction($functionName);

        self::assertStringMatchesFormat($expectedString, (string) $functionReflection);
    }

    public function testToStringForInternal(): void
    {
        $reflector          = new DefaultReflector(new PhpInternalSourceLocator($this->astLocator, (BetterReflectionSingleton::instance()->sourceStubber())));
        $functionReflection = $reflector->reflectFunction('phpversion');

        self::assertStringMatchesFormat("Function [ <internal:standard> function phpversion ] {\n\n  - Parameters [1] {\n    Parameter #0 [ <required> ?string \$extension ]\n  }\n  - Return [ string|false ]\n}", (string) $functionReflection);
    }

    public function testToStringWithNoFileName(): void
    {
        $php = '<?php function functionToStringCast() {}';

        $reflector          = new DefaultReflector(new StringSourceLocator($php, $this->astLocator));
        $functionReflection = $reflector->reflectFunction('functionToStringCast');

        self::assertStringStartsWith('Function [ <user> function functionToStringCast ]', (string) $functionReflection);
    }
}
