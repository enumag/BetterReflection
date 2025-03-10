<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Adapter;

use ArgumentCountError;
use Error;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass as CoreReflectionClass;
use ReflectionException as CoreReflectionException;
use ReflectionProperty as CoreReflectionProperty;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute as ReflectionAttributeAdapter;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass as ReflectionClassAdapter;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionNamedType as ReflectionNamedTypeAdapter;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionProperty as ReflectionPropertyAdapter;
use PHPStan\BetterReflection\Reflection\Exception\NoObjectProvided;
use PHPStan\BetterReflection\Reflection\Exception\NotAnObject;
use PHPStan\BetterReflection\Reflection\Exception\ObjectNotInstanceOfClass;
use PHPStan\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;
use PHPStan\BetterReflection\Reflection\ReflectionClass as BetterReflectionClass;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType as BetterReflectionNamedType;
use PHPStan\BetterReflection\Reflection\ReflectionProperty as BetterReflectionProperty;
use stdClass;
use TypeError;

use function array_combine;
use function array_map;
use function get_class_methods;
use function is_array;

#[CoversClass(ReflectionPropertyAdapter::class)]
class ReflectionPropertyTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function coreReflectionMethodNamesProvider(): array
    {
        $methods = get_class_methods(CoreReflectionProperty::class);

        return array_combine($methods, array_map(static function (string $i) : array {
            return [$i];
        }, $methods));
    }

    #[DataProvider('coreReflectionMethodNamesProvider')]
    public function testCoreReflectionMethods(string $methodName): void
    {
        $reflectionPropertyAdapterReflection = new CoreReflectionClass(ReflectionPropertyAdapter::class);

        self::assertTrue($reflectionPropertyAdapterReflection->hasMethod($methodName));
        self::assertSame(ReflectionPropertyAdapter::class, $reflectionPropertyAdapterReflection->getMethod($methodName)->getDeclaringClass()->getName());
    }

    /** @return list<array{0: string, 1: list<mixed>, 2: mixed, 3: string|null, 4: mixed, 5: string|null}> */
    public static function methodExpectationProvider(): array
    {
        return [
            ['__toString', [], 'string', null, 'string', null],
            ['getName', [], 'name', null, 'name', null],
            ['isPublic', [], true, null, true, null],
            ['isPrivate', [], true, null, true, null],
            ['isProtected', [], true, null, true, null],
            ['isStatic', [], true, null, true, null],
            ['isDefault', [], true, null, true, null],
            ['getModifiers', [], 123, null, 123, null],
            ['getDocComment', [], null, null, false, null],
            ['hasType', [], true, null, true, null],
            ['hasDefaultValue', [], true, null, true, null],
            ['getDefaultValue', [], null, null, null, null],
            ['isPromoted', [], true, null, true, null],
            ['isReadOnly', [], true, null, true, null],
        ];
    }

    /** @param list<mixed> $args
     * @param mixed $returnValue
     * @param mixed $expectedReturnValue */
    #[DataProvider('methodExpectationProvider')]
    public function testAdapterMethods(string $methodName, array $args, $returnValue, ?string $expectedException, $expectedReturnValue, ?string $expectedReturnValueInstance) : void
    {
        $reflectionStub = $this->createMock(BetterReflectionProperty::class);
        if ($expectedException === null) {
            $reflectionStub->expects($this->once())
                ->method($methodName)
                ->with(...$args)
                ->willReturn($returnValue);
        }
        $adapter = new ReflectionPropertyAdapter($reflectionStub);
        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }
        $actualReturnValue = $adapter->{$methodName}(...$args);
        if ($expectedReturnValue !== null) {
            self::assertSame($expectedReturnValue, $actualReturnValue);
        }
        if ($expectedReturnValueInstance === null) {
            return;
        }
        if (is_array($actualReturnValue)) {
            self::assertNotEmpty($actualReturnValue);
            self::assertContainsOnlyInstancesOf($expectedReturnValueInstance, $actualReturnValue);
        } else {
            self::assertInstanceOf($expectedReturnValueInstance, $actualReturnValue);
        }
    }

    public function testGetDocCommentReturnsFalseWhenNoDocComment(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getDocComment')
            ->willReturn(null);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        self::assertFalse($reflectionPropertyAdapter->getDocComment());
    }

    public function testGetDeclaringClass(): void
    {
        $betterReflectionClass = $this->createMock(BetterReflectionClass::class);
        $betterReflectionClass
            ->method('getName')
            ->willReturn('DeclaringClass');

        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getImplementingClass')
            ->willReturn($betterReflectionClass);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        self::assertInstanceOf(ReflectionClassAdapter::class, $reflectionPropertyAdapter->getDeclaringClass());
        self::assertSame('DeclaringClass', $reflectionPropertyAdapter->getDeclaringClass()->getName());
    }

    public function testGetType(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getType')
            ->willReturn($this->createMock(BetterReflectionNamedType::class));

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        self::assertInstanceOf(ReflectionNamedTypeAdapter::class, method_exists($reflectionPropertyAdapter, 'getType') ? $reflectionPropertyAdapter->getType() : null);
    }

    public function testGetValueReturnsNullWhenNoObject(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('getValue')
            ->willThrowException(NoObjectProvided::create());

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        self::assertNull($reflectionPropertyAdapter->getValue());
    }

    public function testSetValueThrowsErrorWhenNoObject(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('setValue')
            ->willThrowException(NoObjectProvided::create());

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(ArgumentCountError::class);
        $reflectionPropertyAdapter->setValue(null);
    }

    public function testSetValueThrowsErrorWhenNotAnObject(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('setValue')
            ->willThrowException(NotAnObject::fromNonObject('string'));

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(TypeError::class);
        $reflectionPropertyAdapter->setValue('string');
    }

    public function testGetValueThrowsExceptionWhenObjectNotInstanceOfClass(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('getValue')
            ->willThrowException(ObjectNotInstanceOfClass::fromClassName('Foo'));

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(CoreReflectionException::class);
        $reflectionPropertyAdapter->getValue(new stdClass());
    }

    public function testSetValueThrowsExceptionWhenObjectNotInstanceOfClass(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('setValue')
            ->willThrowException(ObjectNotInstanceOfClass::fromClassName('Foo'));

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(CoreReflectionException::class);
        $reflectionPropertyAdapter->setValue(new stdClass());
    }

    public function testIsInitializedThrowsExceptionWhenObjectNotInstanceOfClass(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('isPublic')
            ->willReturn(true);
        $betterReflectionProperty
            ->method('isInitialized')
            ->willThrowException(ObjectNotInstanceOfClass::fromClassName('Foo'));

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(CoreReflectionException::class);

        $reflectionPropertyAdapter->isInitialized(new stdClass());
    }

    public function testGetAttributes(): void
    {
        $betterReflectionAttribute1 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute1
            ->method('getName')
            ->willReturn('SomeAttribute');
        $betterReflectionAttribute2 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute2
            ->method('getName')
            ->willReturn('AnotherAttribute');

        $betterReflectionAttributes = [$betterReflectionAttribute1, $betterReflectionAttribute2];

        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getAttributes')
            ->willReturn($betterReflectionAttributes);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);
        $attributes                = method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes() : [];

        self::assertCount(2, $attributes);
        self::assertSame('SomeAttribute', $attributes[0]->getName());
        self::assertSame('AnotherAttribute', $attributes[1]->getName());
    }

    public function testGetAttributesWithName(): void
    {
        /** @phpstan-var class-string $someAttributeClassName */
        $someAttributeClassName = 'SomeAttribute';
        /** @phpstan-var class-string $anotherAttributeClassName */
        $anotherAttributeClassName = 'AnotherAttribute';

        $betterReflectionAttribute1 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute1
            ->method('getName')
            ->willReturn($someAttributeClassName);
        $betterReflectionAttribute2 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute2
            ->method('getName')
            ->willReturn($anotherAttributeClassName);

        $betterReflectionAttributes = [$betterReflectionAttribute1, $betterReflectionAttribute2];

        $betterReflectionProperty = $this->getMockBuilder(BetterReflectionProperty::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributes'])
            ->getMock();

        $betterReflectionProperty
            ->method('getAttributes')
            ->willReturn($betterReflectionAttributes);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);
        $attributes                = method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes($someAttributeClassName) : [];

        self::assertCount(1, $attributes);
        self::assertSame($someAttributeClassName, $attributes[0]->getName());
    }

    public function testGetAttributesWithInstance(): void
    {
        /** @phpstan-var class-string $className */
        $className = 'ClassName';
        /** @phpstan-var class-string $parentClassName */
        $parentClassName = 'ParentClassName';
        /** @phpstan-var class-string $interfaceName */
        $interfaceName = 'InterfaceName';

        $betterReflectionAttributeClass1 = $this->createMock(BetterReflectionClass::class);
        $betterReflectionAttributeClass1
            ->method('getName')
            ->willReturn($className);
        $betterReflectionAttributeClass1
            ->method('isSubclassOf')
            ->willReturnMap([
                [$parentClassName, true],
                [$interfaceName, false],
            ]);
        $betterReflectionAttributeClass1
            ->method('implementsInterface')
            ->willReturnMap([
                [$parentClassName, false],
                [$interfaceName, false],
            ]);

        $betterReflectionAttribute1 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute1
            ->method('getClass')
            ->willReturn($betterReflectionAttributeClass1);

        $betterReflectionAttributeClass2 = $this->createMock(BetterReflectionClass::class);
        $betterReflectionAttributeClass2
            ->method('getName')
            ->willReturn('Whatever');
        $betterReflectionAttributeClass2
            ->method('isSubclassOf')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, false],
            ]);
        $betterReflectionAttributeClass2
            ->method('implementsInterface')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, true],
            ]);

        $betterReflectionAttribute2 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute2
            ->method('getClass')
            ->willReturn($betterReflectionAttributeClass2);

        $betterReflectionAttributeClass3 = $this->createMock(BetterReflectionClass::class);
        $betterReflectionAttributeClass3
            ->method('getName')
            ->willReturn('Whatever');
        $betterReflectionAttributeClass3
            ->method('isSubclassOf')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, true],
                [$interfaceName, false],
            ]);
        $betterReflectionAttributeClass3
            ->method('implementsInterface')
            ->willReturnMap([
                [$className, false],
                [$parentClassName, false],
                [$interfaceName, true],
            ]);

        $betterReflectionAttribute3 = $this->createMock(BetterReflectionAttribute::class);
        $betterReflectionAttribute3
            ->method('getClass')
            ->willReturn($betterReflectionAttributeClass3);

        $betterReflectionAttributes = [
            $betterReflectionAttribute1,
            $betterReflectionAttribute2,
            $betterReflectionAttribute3,
        ];

        $betterReflectionProperty = $this->getMockBuilder(BetterReflectionProperty::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributes'])
            ->getMock();

        $betterReflectionProperty
            ->method('getAttributes')
            ->willReturn($betterReflectionAttributes);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        self::assertCount(1, method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes($className, ReflectionAttributeAdapter::IS_INSTANCEOF) : []);
        self::assertCount(2, method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes($parentClassName, ReflectionAttributeAdapter::IS_INSTANCEOF) : []);
        self::assertCount(2, method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes($interfaceName, ReflectionAttributeAdapter::IS_INSTANCEOF) : []);
    }

    public function testGetAttributesThrowsExceptionForInvalidFlags(): void
    {
        $betterReflectionProperty  = $this->createMock(BetterReflectionProperty::class);
        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);

        $this->expectException(Error::class);
        method_exists($reflectionPropertyAdapter, 'getAttributes') ? $reflectionPropertyAdapter->getAttributes(null, 123) : [];
    }

    public function testPropertyName(): void
    {
        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getName')
            ->willReturn('foo');

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);
        self::assertSame('foo', $reflectionPropertyAdapter->name);
    }

    public function testPropertyClass(): void
    {
        $betterReflectionClass = $this->createMock(BetterReflectionClass::class);
        $betterReflectionClass
            ->method('getName')
            ->willReturn('Foo');

        $betterReflectionProperty = $this->createMock(BetterReflectionProperty::class);
        $betterReflectionProperty
            ->method('getImplementingClass')
            ->willReturn($betterReflectionClass);

        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);
        self::assertSame('Foo', $reflectionPropertyAdapter->class);
    }

    public function testUnknownProperty(): void
    {
        $betterReflectionProperty  = $this->createMock(BetterReflectionProperty::class);
        $reflectionPropertyAdapter = new ReflectionPropertyAdapter($betterReflectionProperty);
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Property PHPStan\BetterReflection\Reflection\Adapter\ReflectionProperty::$foo does not exist.');
        /** @phpstan-ignore-next-line */
        $reflectionPropertyAdapter->foo;
    }
}
