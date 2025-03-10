<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute as CoreReflectionAttribute;
use ReflectionClass as CoreReflectionClass;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute as ReflectionAttributeAdapter;
use PHPStan\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;

use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\AttributeThatAcceptsArgument;
use Roave\BetterReflectionTest\Fixture\ClassWithAttributeThatAcceptsArgument;
use Roave\BetterReflectionTest\Fixture\SomeEnum;
use function array_combine;
use function array_map;
use function get_class_methods;

#[CoversClass(ReflectionAttributeAdapter::class)]
class ReflectionAttributeTest extends TestCase
{

    /** @return array<string, array{0: string}> */
    public static function coreReflectionMethodNamesProvider(): array
    {
        $methods = get_class_methods(CoreReflectionAttribute::class);

        return array_combine($methods, array_map(static function (string $i) : array {
            return [$i];
        }, $methods));
    }

    #[DataProvider('coreReflectionMethodNamesProvider')]
    public function testCoreReflectionMethods(string $methodName): void
    {
        $reflectionTypeAdapterReflection = new CoreReflectionClass(ReflectionAttributeAdapter::class);

        self::assertTrue($reflectionTypeAdapterReflection->hasMethod($methodName));
        self::assertSame(ReflectionAttributeAdapter::class, $reflectionTypeAdapterReflection->getMethod($methodName)->getDeclaringClass()->getName());
    }

    /** @return list<array{0: string, 1: class-string|null, 2: mixed, 3: list<mixed>}> */
    public static function methodExpectationProvider(): array
    {
        return [
            ['__toString', null, '', []],
            ['getName', null, '', []],
            ['getTarget', null, 1, []],
            ['isRepeated', null, false, []],
            ['getArguments', null, [], []],
        ];
    }

    /** @param list<mixed> $args
     * @param mixed $returnValue */
    #[DataProvider('methodExpectationProvider')]
    public function testAdapterMethods(string $methodName, ?string $expectedException, $returnValue, array $args): void
    {
        $reflectionStub = $this->createMock(BetterReflectionAttribute::class);

        if ($expectedException === null) {
            $reflectionStub->expects($this->once())
                ->method($methodName)
                ->with(...$args)
                ->will($this->returnValue($returnValue));
        }

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        $adapter = new ReflectionAttributeAdapter($reflectionStub);
        $adapter->{$methodName}(...$args);
    }

    public function testNewInstanceWithEnum(): void
    {
        $astLocator = BetterReflectionSingleton::instance()->astLocator();
        $path = __DIR__ . '/../../Fixture/Attributes.php';
        require_once($path);

        $betterReflection = BetterReflectionSingleton::instance();
        $reflector  = new DefaultReflector(new AggregateSourceLocator([new SingleFileSourceLocator($path, $astLocator), new PhpInternalSourceLocator($astLocator, $betterReflection->sourceStubber())]));
        $reflection = $reflector->reflectClass(ClassWithAttributeThatAcceptsArgument::class);
        $attributes = $reflection->getAttributesByName(AttributeThatAcceptsArgument::class);
        $this->assertCount(1, $attributes);
        $adapter = new ReflectionAttributeAdapter($attributes[0]);
        $instance = $adapter->newInstance();
        $this->assertInstanceOf(AttributeThatAcceptsArgument::class, $instance);
        $this->assertInstanceOf(SomeEnum::class, $instance->e);
        $this->assertSame('ONE', $instance->e->name);
        $this->assertSame(1, $instance->e->value);
    }
}
