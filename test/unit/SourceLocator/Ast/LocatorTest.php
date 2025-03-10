<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Identifier\Identifier;
use PHPStan\BetterReflection\Identifier\IdentifierType;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflection\ReflectionConstant;
use PHPStan\BetterReflection\Reflection\ReflectionFunction;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\SourceLocator\Ast\Exception\ParseToAstFailure;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\Located\LocatedSource;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;

#[CoversClass(Locator::class)]
class LocatorTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Ast\Locator
     */
    private $locator;

    protected function setUp(): void
    {
        parent::setUp();

        $betterReflection = BetterReflectionSingleton::instance();

        $this->locator = new Locator($betterReflection->phpParser());
    }

    private function getIdentifier(string $name, string $type): Identifier
    {
        return new Identifier($name, new IdentifierType($type));
    }

    public function testReflectingWithinNamespace(): void
    {
        $php = '<?php
        namespace Foo;
        class Bar {}
        ';

        $classInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'Foo\Bar'), $this->getIdentifier('Foo\Bar', IdentifierType::IDENTIFIER_CLASS));

        self::assertInstanceOf(ReflectionClass::class, $classInfo);
    }

    public function testTheReflectionLookupIsCaseInsensitive(): void
    {
        $php = '<?php
        namespace Foo;
        class Bar {}
        ';

        $classInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'Foo\Bar'), $this->getIdentifier('Foo\BAR', IdentifierType::IDENTIFIER_CLASS));

        self::assertInstanceOf(ReflectionClass::class, $classInfo);
    }

    public function testReflectingTopLevelClass(): void
    {
        $php = '<?php
        class Foo {}
        ';

        $classInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'Foo'), $this->getIdentifier('Foo', IdentifierType::IDENTIFIER_CLASS));

        self::assertInstanceOf(ReflectionClass::class, $classInfo);
    }

    public function testReflectingTopLevelFunction(): void
    {
        $php = '<?php
        function foo() {}
        ';

        $functionInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'foo'), $this->getIdentifier('foo', IdentifierType::IDENTIFIER_FUNCTION));

        self::assertInstanceOf(ReflectionFunction::class, $functionInfo);
    }

    public function testReflectingTopLevelConstantByConst(): void
    {
        $php = '<?php
        const FOO = 1;
        ';

        $constantInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'FOO'), $this->getIdentifier('FOO', IdentifierType::IDENTIFIER_CONSTANT));

        self::assertInstanceOf(ReflectionConstant::class, $constantInfo);
    }

    public function testReflectingTopLevelConstantByDefine(): void
    {
        $php = '<?php
        define("FOO", 1);
        ';

        $constantInfo = $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'FOO'), $this->getIdentifier('FOO', IdentifierType::IDENTIFIER_CONSTANT));

        self::assertInstanceOf(ReflectionConstant::class, $constantInfo);
    }

    public function testReflectThrowsExceptionWhenClassNotFoundAndNoNodesExist(): void
    {
        $php = '<?php';

        $this->expectException(IdentifierNotFound::class);
        $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'Foo'), $this->getIdentifier('Foo', IdentifierType::IDENTIFIER_CLASS));
    }

    public function testReflectThrowsExceptionWhenClassNotFoundButNodesExist(): void
    {
        $php = "<?php
        namespace Foo;
        echo 'Hello world';
        ";

        $this->expectException(IdentifierNotFound::class);
        $this->locator->findReflection(new DefaultReflector(new StringSourceLocator($php, $this->locator)), new LocatedSource($php, 'Foo'), $this->getIdentifier('Foo', IdentifierType::IDENTIFIER_CLASS));
    }

    public function testFindReflectionsOfTypeThrowsParseToAstFailureExceptionWithInvalidCode(): void
    {
        $phpCode = '<?php syntax error';

        $identifierType = new IdentifierType(IdentifierType::IDENTIFIER_CLASS);
        $sourceLocator  = new StringSourceLocator($phpCode, $this->locator);
        $reflector      = new DefaultReflector($sourceLocator);

        $locatedSource = new LocatedSource($phpCode, 'Whatever');

        $this->expectException(ParseToAstFailure::class);
        $this->locator->findReflectionsOfType($reflector, $locatedSource, $identifierType);
    }

    public function testFindReflectionCannotFindIdentificatorWhenLocatorDoesNotContainName(): void
    {
        $phpCode = '<?php function () {};';

        $identifier    = $this->getIdentifier('Foo', IdentifierType::IDENTIFIER_CLASS);
        $sourceLocator = new StringSourceLocator($phpCode, $this->locator);
        $reflector     = new DefaultReflector($sourceLocator);

        $locatedSource = new LocatedSource($phpCode, null);

        $this->expectException(IdentifierNotFound::class);
        $this->locator->findReflection($reflector, $locatedSource, $identifier);
    }
}
