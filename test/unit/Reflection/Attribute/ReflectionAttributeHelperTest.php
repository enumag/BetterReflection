<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use PhpParser\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use PHPStan\BetterReflection\Reflection\ReflectionAttribute;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflector\Reflector;

#[CoversClass(ReflectionAttributeHelper::class)]
class ReflectionAttributeHelperTest extends TestCase
{
    public function testCreateAttributes(): void
    {
        $ast             = $this->createMock(Node\Stmt\Class_::class);
        $ast->attrGroups = [
            new Node\AttributeGroup([
                new Node\Attribute(new Node\Name('SomeAttr')),
                new Node\Attribute(new Node\Name('AnotherAttr')),
                new Node\Attribute(new Node\Name('AnotherAttr')),
            ]),
        ];

        $reflection = $this->createMock(ReflectionClass::class);
        $attributes = ReflectionAttributeHelper::createAttributes($this->createMock(Reflector::class), $reflection, $ast->attrGroups);

        self::assertCount(3, $attributes);

        self::assertFalse($attributes[0]->isRepeated());
        self::assertTrue($attributes[1]->isRepeated());
        self::assertTrue($attributes[2]->isRepeated());
    }

    public function testFilterAttributesByName(): void
    {
        $attribute1 = $this->createMock(ReflectionAttribute::class);
        $attribute1
            ->method('getName')
            ->willReturn('SomeAttr');

        $attribute2 = $this->createMock(ReflectionAttribute::class);
        $attribute2
            ->method('getName')
            ->willReturn('AnotherAttr');

        $attribute3 = $this->createMock(ReflectionAttribute::class);
        $attribute3
            ->method('getName')
            ->willReturn('AnotherAttr');

        $attributes = [
            $attribute1,
            $attribute2,
            $attribute3,
        ];

        self::assertCount(1, ReflectionAttributeHelper::filterAttributesByName($attributes, 'SomeAttr'));
        self::assertCount(2, ReflectionAttributeHelper::filterAttributesByName($attributes, 'AnotherAttr'));
    }

    public function testFilterAttributesByInstance(): void
    {
        /** @phpstan-var class-string $className */
        $className = 'ClassName';
        /** @phpstan-var class-string $parentClassName */
        $parentClassName = 'ParentClassName';
        /** @phpstan-var class-string $interfaceName */
        $interfaceName = 'InterfaceName';

        $attributeClass1 = $this->createMock(ReflectionClass::class);
        $attributeClass1
            ->method('getName')
            ->willReturn($className);
        $attributeClass1
            ->method('isSubclassOf')
            ->willReturnMap([
                [$parentClassName, true],
                [$interfaceName, false],
            ]);
        $attributeClass1
            ->method('implementsInterface')
            ->willReturnMap([
                [$parentClassName, false],
                [$interfaceName, false],
            ]);

        $attribute1 = $this->createMock(ReflectionAttribute::class);
        $attribute1
            ->method('getClass')
            ->willReturn($attributeClass1);

        $attributeClass2 = $this->createMock(ReflectionClass::class);
        $attributeClass2
            ->method('getName')
            ->willReturn('Whatever');
        $attributeClass2
            ->method('isSubclassOf')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, false],
            ]);
        $attributeClass2
            ->method('implementsInterface')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, true],
            ]);

        $attribute2 = $this->createMock(ReflectionAttribute::class);
        $attribute2
            ->method('getClass')
            ->willReturn($attributeClass2);

        $attributeClass3 = $this->createMock(ReflectionClass::class);
        $attributeClass3
            ->method('getName')
            ->willReturn('Whatever');
        $attributeClass3
            ->method('isSubclassOf')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, true],
                [$interfaceName, false],
            ]);
        $attributeClass3
            ->method('implementsInterface')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, true],
            ]);

        $attribute3 = $this->createMock(ReflectionAttribute::class);
        $attribute3
            ->method('getClass')
            ->willReturn($attributeClass3);

        $attributes = [
            $attribute1,
            $attribute2,
            $attribute3,
        ];

        self::assertCount(1, ReflectionAttributeHelper::filterAttributesByInstance($attributes, $className));
        self::assertCount(2, ReflectionAttributeHelper::filterAttributesByInstance($attributes, $parentClassName));
        self::assertCount(2, ReflectionAttributeHelper::filterAttributesByInstance($attributes, $interfaceName));
    }
}
