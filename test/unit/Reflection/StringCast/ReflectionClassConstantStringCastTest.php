<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\StringCast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionClassConstantStringCast;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\StringCastConstants;

#[CoversClass(ReflectionClassConstantStringCast::class)]
class ReflectionClassConstantStringCastTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Ast\Locator
     */
    private $astLocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->astLocator = BetterReflectionSingleton::instance()->astLocator();
    }

    /** @return list<array{0: non-empty-string, 1: string}> */
    public static function toStringProvider(): array
    {
        return [
            ['PUBLIC_CONSTANT', "Constant [ public boolean PUBLIC_CONSTANT ] { 1 }\n"],
            ['PROTECTED_CONSTANT', "Constant [ protected integer PROTECTED_CONSTANT ] { 0 }\n"],
            ['PRIVATE_CONSTANT', "Constant [ private string PRIVATE_CONSTANT ] { string }\n"],
            ['NO_VISIBILITY_CONSTANT', "Constant [ public array NO_VISIBILITY_CONSTANT ] { Array }\n"],
            ['FINAL_CONSTANT', "Constant [ final public string FINAL_CONSTANT ] { final }\n"],
        ];
    }

    /** @param non-empty-string $constantName */
    #[DataProvider('toStringProvider')]
    public function testToString(string $constantName, string $expectedString): void
    {
        $reflector       = new DefaultReflector(new SingleFileSourceLocator(__DIR__ . '/../../Fixture/StringCastClassConstants.php', $this->astLocator));
        $classReflection = $reflector->reflectClass(StringCastConstants::class);

        self::assertSame($expectedString, (string) $classReflection->getConstant($constantName));
    }
}
