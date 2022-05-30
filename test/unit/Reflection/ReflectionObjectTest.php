<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use InvalidArgumentException;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;
use ReflectionObject as CoreReflectionObject;
use ReflectionProperty as CoreReflectionProperty;
use PHPStan\BetterReflection\Reflection\Exception\Uncloneable;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflection\ReflectionObject;
use PHPStan\BetterReflection\Reflection\ReflectionProperty;
use PHPStan\BetterReflection\Util\FileHelper;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\ClassForHinting;
use Roave\BetterReflectionTest\Fixture\DefaultProperties;
use Roave\BetterReflectionTest\Fixture\FixtureInterfaceRequire;
use stdClass;

use function realpath;

/**
 * @covers \PHPStan\BetterReflection\Reflection\ReflectionObject
 */
class ReflectionObjectTest extends TestCase
{
    /**
     * @return Node[]
     */
    private function parse(string $code): array
    {
        return BetterReflectionSingleton::instance()->phpParser()->parse($code);
    }

    public function anonymousClassInstancesProvider(): array
    {
        $file = FileHelper::normalizeWindowsPath(realpath(__DIR__ . '/../Fixture/AnonymousClassInstances.php'));

        $anonymousClasses = require $file;

        return [
            [$anonymousClasses[0], $file, 3, 9],
            [$anonymousClasses[1], $file, 11, 17],
        ];
    }

    /**
     * @dataProvider anonymousClassInstancesProvider
     */
    public function testReflectionForAnonymousClass(object $anonymousClass, string $file, int $startLine, int $endLine): void
    {
        $classInfo = ReflectionObject::createFromInstance($anonymousClass);

        self::assertTrue($classInfo->isAnonymous());
        self::assertFalse($classInfo->inNamespace());
        self::assertStringStartsWith(ReflectionClass::ANONYMOUS_CLASS_NAME_PREFIX, $classInfo->getName());
        self::assertSame($file, $classInfo->getFileName());
        self::assertSame($startLine, $classInfo->getStartLine());
        self::assertSame($endLine, $classInfo->getEndLine());
    }

    public function testReflectionForAnonymousClassWithInterface(): void
    {
        $file = FileHelper::normalizeWindowsPath(realpath(__DIR__ . '/../Fixture/AnonymousClassInstanceWithInterfaceForRequire.php'));

        $anonymousClass = require $file;

        $classInfo = ReflectionObject::createFromInstance($anonymousClass);

        self::assertTrue($classInfo->isAnonymous());
        self::assertFalse($classInfo->inNamespace());
        self::assertStringStartsWith(FixtureInterfaceRequire::class, $classInfo->getName());
        self::assertContains(FixtureInterfaceRequire::class, $classInfo->getInterfaceNames());
        self::assertTrue($classInfo->isInstantiable());
    }

    public function testReflectionWorksWithInternalClasses(): void
    {
        $foo = new stdClass();

        $classInfo = ReflectionObject::createFromInstance($foo);
        self::assertInstanceOf(ReflectionObject::class, $classInfo);
        self::assertSame('stdClass', $classInfo->getName());
        self::assertTrue($classInfo->isInternal());
        self::assertSame('Core', $classInfo->getExtensionName());
    }

    public function testReflectionWorksWithEvaledClasses(): void
    {
        $foo = new ClassForHinting();

        $classInfo = ReflectionObject::createFromInstance($foo);
        self::assertInstanceOf(ReflectionObject::class, $classInfo);
        self::assertSame(ClassForHinting::class, $classInfo->getName());
        self::assertFalse($classInfo->isInternal());
        self::assertNull($classInfo->getExtensionName());
    }

    public function testReflectionWorksWithDynamicallyDeclaredMembers(): void
    {
        $foo      = new ClassForHinting();
        $foo->bar = 'huzzah';

        $classInfo = ReflectionObject::createFromInstance($foo);
        $propInfo  = $classInfo->getProperty('bar');

        self::assertInstanceOf(ReflectionProperty::class, $propInfo);
        self::assertSame('bar', $propInfo->getName());
        self::assertFalse($propInfo->isDefault());
    }

    public function testExceptionThrownWhenInvalidInstanceGiven(): void
    {
        $foo      = new ClassForHinting();
        $foo->bar = 'huzzah';

        $classInfo = ReflectionObject::createFromInstance($foo);

        $mockClass = $this->createMock(ReflectionClass::class);

        $reflectionObjectReflection = new CoreReflectionObject($classInfo);

        $reflectionObjectObjectReflection = $reflectionObjectReflection->getProperty('object');
        $reflectionObjectObjectReflection->setAccessible(true);
        $reflectionObjectObjectReflection->setValue($classInfo, new stdClass());

        $reflectionObjectReflectionClassReflection = $reflectionObjectReflection->getProperty('reflectionClass');
        $reflectionObjectReflectionClassReflection->setAccessible(true);
        $reflectionObjectReflectionClassReflection->setValue($classInfo, $mockClass);

        $this->expectException(InvalidArgumentException::class);
        $classInfo->getProperties();
    }

    public function testGetRuntimePropertiesWithFilter(): void
    {
        $foo      = new stdClass();
        $foo->bar = 'huzzah';

        $classInfo = ReflectionObject::createFromInstance($foo);

        self::assertEmpty($classInfo->getProperties(CoreReflectionProperty::IS_STATIC));
        self::assertCount(1, $classInfo->getProperties(CoreReflectionProperty::IS_PUBLIC));
        self::assertEmpty($classInfo->getProperties(CoreReflectionProperty::IS_PROTECTED));
        self::assertEmpty($classInfo->getProperties(CoreReflectionProperty::IS_PRIVATE));
    }

    public function testGetRuntimeImmediatePropertiesWithFilter(): void
    {
        $foo      = new stdClass();
        $foo->bar = 'huzzah';

        $classInfo = ReflectionObject::createFromInstance($foo);

        self::assertEmpty($classInfo->getImmediateProperties(CoreReflectionProperty::IS_STATIC));
        self::assertCount(1, $classInfo->getImmediateProperties(CoreReflectionProperty::IS_PUBLIC));
        self::assertEmpty($classInfo->getImmediateProperties(CoreReflectionProperty::IS_PROTECTED));
        self::assertEmpty($classInfo->getImmediateProperties(CoreReflectionProperty::IS_PRIVATE));
    }

    public function testRuntimePropertyCannotBePromoted(): void
    {
        $foo      = new stdClass();
        $foo->bar = 'huzzah';

        $classInfo    = ReflectionObject::createFromInstance($foo);
        $propertyInfo = $classInfo->getProperty('bar');

        self::assertInstanceOf(ReflectionProperty::class, $propertyInfo);
        self::assertFalse($propertyInfo->isPromoted());
        self::assertSame(0, $propertyInfo->getPositionInAst());
    }

    public function testGetDefaultPropertiesShouldIgnoreRuntimeProperty(): void
    {
        $object                     = new DefaultProperties();
        $object->notDefaultProperty = null;

        $classInfo = ReflectionObject::createFromInstance($object);

        self::assertSame([
            'fromTrait' => 'anything',
            'hasDefault' => 'const',
            'hasNullAsDefault' => null,
            'noDefault' => null,
            'hasDefaultWithType' => 123,
            'hasNullAsDefaultWithType' => null,
            'noDefaultWithType' => null,
        ], $classInfo->getDefaultProperties());
    }

    public function testCannotClone(): void
    {
        $classInfo = ReflectionObject::createFromInstance(new stdClass());

        $this->expectException(Uncloneable::class);
        clone $classInfo;
    }
}
