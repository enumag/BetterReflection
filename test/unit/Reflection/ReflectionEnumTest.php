<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\ReflectionEnum;
use PHPStan\BetterReflection\Reflection\ReflectionEnumCase;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\IntEnum;
use Roave\BetterReflectionTest\Fixture\PureEnum;
use Roave\BetterReflectionTest\Fixture\StringEnum;

/**
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionEnum
 */
class ReflectionEnumTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Ast\Locator
     */
    private $astLocator;

    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;

    public function setUp(): void
    {
        parent::setUp();

        $this->astLocator = BetterReflectionSingleton::instance()->astLocator();
        $this->reflector  = new DefaultReflector(new SingleFileSourceLocator(__DIR__ . '/../Fixture/Enums.php', $this->astLocator));
    }

    public function dataCanReflect(): array
    {
        return [
            [PureEnum::class],
            [IntEnum::class],
            [StringEnum::class],
        ];
    }

    /**
     * @dataProvider dataCanReflect
     */
    public function testCanReflect(string $enumName): void
    {
        $enumReflection = $this->reflector->reflectClass($enumName);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);
    }

    public function dataHasAndGetCase(): array
    {
        return [
            ['ONE', true],
            ['TWO', true],
            ['THREE', true],
            ['FOUR', false],
        ];
    }

    /**
     * @dataProvider dataHasAndGetCase
     */
    public function testHasAndGetCase(string $caseName, bool $exists): void
    {
        $enumReflection = $this->reflector->reflectClass(PureEnum::class);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);

        self::assertSame($exists, $enumReflection->hasCase($caseName));

        $case = $enumReflection->getCase($caseName);

        if ($exists) {
            self::assertInstanceOf(ReflectionEnumCase::class, $case);
        } else {
            self::assertNull($case);
        }
    }

    public function dataGetCases(): array
    {
        return [
            [PureEnum::class, 3],
            [IntEnum::class, 4],
            [StringEnum::class, 5],
        ];
    }

    /**
     * @dataProvider dataGetCases
     */
    public function testGetCases(string $enumName, int $casesCount): void
    {
        $enumReflection = $this->reflector->reflectClass($enumName);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);

        $cases = $enumReflection->getCases();

        self::assertCount($casesCount, $cases);
        self::containsOnlyInstancesOf(ReflectionEnumCase::class, $cases);
    }

    public function dataIsBacked(): array
    {
        return [
            [PureEnum::class, false],
            [IntEnum::class, true],
            [StringEnum::class, true],
        ];
    }

    /**
     * @dataProvider dataIsBacked
     */
    public function testIsBacked(string $enumName, bool $isBacked): void
    {
        $enumReflection = $this->reflector->reflectClass($enumName);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);
        self::assertSame($isBacked, $enumReflection->isBacked());
    }

    public function dataGetBackingType(): array
    {
        return [
            [IntEnum::class, 'int'],
            [StringEnum::class, 'string'],
        ];
    }

    /**
     * @dataProvider dataGetBackingType
     */
    public function testGetBackingType(string $enumName, ?string $expectedBackingType): void
    {
        $enumReflection = $this->reflector->reflectClass($enumName);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);

        $backingType = $enumReflection->getBackingType();

        if ($expectedBackingType === null) {
            self::assertNull($backingType);
        } else {
            self::assertInstanceOf(ReflectionNamedType::class, $backingType);
            self::assertSame($expectedBackingType, $backingType->__toString());
        }
    }

    public function testGetBackingTypeExceptionForPureEnum(): void
    {
        $enumReflection = $this->reflector->reflectClass(PureEnum::class);

        self::assertInstanceOf(ReflectionEnum::class, $enumReflection);

        self::expectException(LogicException::class);
        $enumReflection->getBackingType();
    }
}
