<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass as CoreReflectionClass;
use PHPStan\BetterReflection\Identifier\Identifier;
use PHPStan\BetterReflection\Identifier\IdentifierType;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Located\EvaledLocatedSource;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\SourceStubber;
use PHPStan\BetterReflection\SourceLocator\Type\EvaledCodeSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;

use function sprintf;
use function uniqid;

#[CoversClass(EvaledCodeSourceLocator::class)]
class EvaledCodeSourceLocatorTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Ast\Locator
     */
    private $astLocator;

    /**
     * @var \PHPStan\BetterReflection\SourceLocator\SourceStubber\SourceStubber
     */
    private $sourceStubber;

    protected function setUp(): void
    {
        parent::setUp();

        $betterReflection = BetterReflectionSingleton::instance();

        $this->astLocator    = $betterReflection->astLocator();
        $this->sourceStubber = $betterReflection->sourceStubber();
    }

    /**
     * @return \PHPStan\BetterReflection\Reflector\Reflector|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getMockReflector()
    {
        return $this->createMock(Reflector::class);
    }

    public function testCanReflectEvaledClass(): void
    {
        $className = uniqid('foo', false);

        eval('class ' . $className . ' {function foo(){}}');

        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);

        $reflection = $locator->locateIdentifier($this->getMockReflector(), new Identifier($className, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        self::assertInstanceOf(ReflectionClass::class, $reflection);

        $source = $reflection->getLocatedSource();

        self::assertInstanceOf(EvaledLocatedSource::class, $source);
        self::assertStringMatchesFormat('%Aclass%A' . $className . '%A', $source->getSource());
    }

    public function testCanReflectEvaledInterface(): void
    {
        $interfaceName = uniqid('foo', false);

        eval('interface ' . $interfaceName . ' {function foo();}');

        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);

        $reflection = $locator->locateIdentifier($this->getMockReflector(), new Identifier($interfaceName, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        self::assertInstanceOf(ReflectionClass::class, $reflection);
        self::assertInstanceOf(EvaledLocatedSource::class, $reflection->getLocatedSource());
        self::assertStringMatchesFormat('%Ainterface%A' . $interfaceName . '%A', $reflection->getLocatedSource()->getSource());
    }

    public function testCanReflectEvaledTrait(): void
    {
        $traitName = uniqid('foo', false);

        eval('trait ' . $traitName . ' {function foo(){}}');

        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);

        $reflection = $locator->locateIdentifier($this->getMockReflector(), new Identifier($traitName, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        self::assertInstanceOf(ReflectionClass::class, $reflection);
        self::assertInstanceOf(EvaledLocatedSource::class, $reflection->getLocatedSource());
        self::assertStringMatchesFormat('%Atrait%A' . $traitName . '%A', $reflection->getLocatedSource()->getSource());
    }

    public function testCanReflectEvaledEnum(): void
    {
        $enumName = uniqid('foo', false);

        eval(sprintf('enum %s {case ENUM_CASE;}', $enumName));

        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);

        $reflection = $locator->locateIdentifier($this->getMockReflector(), new Identifier($enumName, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        self::assertInstanceOf(ReflectionClass::class, $reflection);
        self::assertInstanceOf(EvaledLocatedSource::class, $reflection->getLocatedSource());
        self::assertStringMatchesFormat('%Aenum%A' . $enumName . '%A', $reflection->getLocatedSource()->getSource());
    }

    public function testCanReflectEvaledLocatedSourceClass(): void
    {
        $reflector = new DefaultReflector(new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber));
        $className = uniqid('foo', false);

        eval('class ' . $className . ' {function foo($bar = "baz") {}}');

        $class = $reflector->reflectClass($className);

        self::assertInstanceOf(ReflectionClass::class, $class);
        self::assertSame($className, $class->getName());
        self::assertFalse($class->isInternal());
        self::assertTrue($class->isUserDefined());
        self::assertNull($class->getFileName());
        self::assertCount(1, $class->getMethods());
    }

    public function testCannotReflectRequiredClass(): void
    {
        self::assertNull((new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber))
            ->locateIdentifier($this->getMockReflector(), new Identifier(self::class, new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testReturnsNullForNonExistentCode(): void
    {
        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);
        self::assertNull($locator->locateIdentifier($this->getMockReflector(), new Identifier('Foo\Bar', new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testReturnsNullForFunctions(): void
    {
        $locator = new EvaledCodeSourceLocator($this->astLocator, $this->sourceStubber);
        self::assertNull($locator->locateIdentifier($this->getMockReflector(), new Identifier('foo', new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION))));
    }

    public function testReturnsNullForMissingStub(): void
    {
        $sourceStubber = $this->createMock(SourceStubber::class);
        $sourceStubber
            ->method('generateClassStub')
            ->with(CoreReflectionClass::class)
            ->willReturn(null);

        $sourceLocator = new EvaledCodeSourceLocator($this->astLocator, $sourceStubber);

        self::assertNull($sourceLocator->locateIdentifier($this->getMockReflector(), new Identifier(CoreReflectionClass::class, new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }
}
