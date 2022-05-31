<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use PhpParser\Node;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\ReflectionIntersectionType;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType;
use PHPStan\BetterReflection\Reflection\ReflectionParameter;
use PHPStan\BetterReflection\Reflection\ReflectionType;
use PHPStan\BetterReflection\Reflection\ReflectionUnionType;
use PHPStan\BetterReflection\Reflector\Reflector;

/**
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionType
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionNamedType
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionUnionType
 */
class ReflectionTypeTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionParameter
     */
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = $this->createMock(Reflector::class);
        $this->owner     = $this->createMock(ReflectionParameter::class);
    }

    public function dataProvider(): array
    {
        return [
            [new Node\Name('A'), false, ReflectionNamedType::class, 'A', false],
            [new Node\Identifier('string'), false, ReflectionNamedType::class, 'string', false],
            'Forcing a type to be nullable turns it into a `T|null` ReflectionUnionType' => [
                new Node\Identifier('string'),
                true,
                ReflectionUnionType::class,
                'string|null',
                true,
            ],
            'Nullable types are converted into `T|null` ReflectionUnionType instances' => [
                new Node\NullableType(new Node\Identifier('string')),
                false,
                ReflectionUnionType::class,
                'string|null',
                true,
            ],
            [new Node\IntersectionType([new Node\Name('A'), new Node\Name('B')]), false, ReflectionIntersectionType::class, 'A&B', false],
            [new Node\UnionType([new Node\Name('A'), new Node\Name('B')]), false, ReflectionUnionType::class, 'A|B', false],
            'Union types composed of just `null` and a type are kept as `T|null` ReflectionUnionType' => [
                new Node\UnionType([new Node\Name('A'), new Node\Name('null')]),
                false,
                ReflectionUnionType::class,
                'A|null',
                true,
            ],
            'Union types composed of `null` and more than one type are kept as `T|U|null` ReflectionUnionType' => [
                new Node\UnionType([new Node\Name('A'), new Node\Name('B'), new Node\Name('null')]),
                false,
                ReflectionUnionType::class,
                'A|B|null',
                true,
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param \PhpParser\Node\Identifier|\PhpParser\Node\IntersectionType|\PhpParser\Node\Name|\PhpParser\Node\NullableType|\PhpParser\Node\UnionType $node
     */
    public function test($node, bool $forceAllowsNull, string $expectedReflectionClass, string $expectedTypeAsString, bool $expectedAllowsNull): void
    {
        $reflectionType = ReflectionType::createFromNode($this->reflector, $this->owner, $node, $forceAllowsNull);
        self::assertInstanceOf($expectedReflectionClass, $reflectionType);
        self::assertSame($expectedTypeAsString, $reflectionType->__toString());
        self::assertSame($expectedAllowsNull, $reflectionType->allowsNull());
        self::assertSame($this->owner, $reflectionType->getOwner());
    }
}
