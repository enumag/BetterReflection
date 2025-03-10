<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\StringCast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionAttributeStringCast;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\ClassWithAttributesForStringCast;

#[CoversClass(ReflectionAttributeStringCast::class)]
class ReflectionAttributeStringCastTest extends TestCase
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
            ['Roave\BetterReflectionTest\Fixture\NoArguments', "Attribute [ Roave\BetterReflectionTest\Fixture\NoArguments ]\n"],
            ['Roave\BetterReflectionTest\Fixture\WithArguments', "Attribute [ Roave\BetterReflectionTest\Fixture\WithArguments ] {\n  - Arguments [4] {\n    Argument #0 [ 'not long string' ]\n    Argument #1 [ 'very long strin...' ]\n    Argument #2 [ arg3 = Array ]\n    Argument #3 [ arg4 = true ]\n  }\n}\n"],
        ];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(string $attributeName, string $expectedString): void
    {
        $reflector           = new DefaultReflector(new SingleFileSourceLocator(__DIR__ . '/../../Fixture/StringCastAttributes.php', $this->astLocator));
        $classReflection     = $reflector->reflectClass(ClassWithAttributesForStringCast::class);
        $attributeReflection = $classReflection->getAttributesByName($attributeName)[0];

        self::assertSame($expectedString, (string) $attributeReflection);
    }
}
