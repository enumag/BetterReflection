<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use PhpParser\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\ReflectionParameter;
use PHPStan\BetterReflection\Reflection\ReflectionType;
use PHPStan\BetterReflection\Reflection\ReflectionUnionType;
use PHPStan\BetterReflection\Reflector\Reflector;

#[CoversClass(ReflectionUnionType::class)]
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

    /** @return list<array{0: Node\UnionType, 1: string, 2: bool}> */
    public static function dataProvider(): array
    {
        return [
            [new Node\UnionType([new Node\Name('\A\Foo'), new Node\Name('Boo')]), '\A\Foo|Boo', false],
            [new Node\UnionType([new Node\Name('A'), new Node\Name('B'), new Node\Identifier('null')]), 'A|B|null', true],
            [new Node\UnionType([new Node\IntersectionType([new Node\Name('A'), new Node\Name('B')]), new Node\Identifier('null')]), '(A&B)|null', true],
            [new Node\UnionType([new Node\IntersectionType([new Node\Name('A'), new Node\Name('B')]), new Node\IntersectionType([new Node\Name('A'), new Node\Name('D')])]), '(A&B)|(A&D)', false],
            [new Node\UnionType([new Node\Identifier('null'), new Node\IntersectionType([new Node\Name('A'), new Node\Name('B')]), new Node\IntersectionType([new Node\Name('A'), new Node\Name('C')])]), 'null|(A&B)|(A&C)', true],
        ];
    }

    #[DataProvider('dataProvider')]
    public function test(Node\UnionType $unionType, string $expectedString, bool $expectedNullable): void
    {
        $typeReflection = new ReflectionUnionType($this->reflector, $this->owner, $unionType);

        self::assertContainsOnlyInstancesOf(ReflectionType::class, $typeReflection->getTypes());
        self::assertSame($expectedString, $typeReflection->__toString());
        self::assertSame($expectedNullable, $typeReflection->allowsNull());
    }

    public function testWithOwner(): void
    {
        $typeReflection = new ReflectionUnionType($this->reflector, $this->owner, new Node\UnionType([new Node\Name('\A\Foo'), new Node\Name('Boo')]));
        $types          = $typeReflection->getTypes();

        self::assertCount(2, $types);

        $owner = $this->createMock(ReflectionParameter::class);

        $cloneTypeReflection = $typeReflection->withOwner($owner);

        self::assertNotSame($typeReflection, $cloneTypeReflection);

        $cloneTypes = $cloneTypeReflection->getTypes();

        self::assertCount(2, $cloneTypes);
        self::assertNotSame($types[0], $cloneTypes[0]);
    }
}
