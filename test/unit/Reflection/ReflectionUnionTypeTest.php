<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use PhpParser\Node;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType;
use PHPStan\BetterReflection\Reflection\ReflectionParameter;
use PHPStan\BetterReflection\Reflection\ReflectionUnionType;
use PHPStan\BetterReflection\Reflector\Reflector;

/**
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionUnionType
 */
class ReflectionUnionTypeTest extends TestCase
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
            [new Node\UnionType([new Node\Name('\A\Foo'), new Node\Name('Boo')]), '\A\Foo|Boo', false],
            [new Node\UnionType([new Node\Name('A'), new Node\Name('B'), new Node\Identifier('null')]), 'A|B|null', true],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function test(Node\UnionType $unionType, string $expectedString, bool $expectedNullable): void
    {
        $typeReflection = new ReflectionUnionType($this->reflector, $this->owner, $unionType);

        self::assertContainsOnlyInstancesOf(ReflectionNamedType::class, $typeReflection->getTypes());
        self::assertSame($expectedString, $typeReflection->__toString());
        self::assertSame($expectedNullable, $typeReflection->allowsNull());
        self::assertSame($this->owner, $typeReflection->getOwner());
    }
}
