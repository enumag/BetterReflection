<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\NodeCompiler;

use ArrayObject;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Name;
use PhpParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\NodeCompiler\CompileNodeToValue;
use PHPStan\BetterReflection\NodeCompiler\CompilerContext;
use PHPStan\BetterReflection\NodeCompiler\Exception\UnableToCompileNode;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\SourceStubber;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use PHPStan\BetterReflection\Util\FileHelper;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use Roave\BetterReflectionTest\Fixture\ClassWithNewInInitializers;
use Roave\BetterReflectionTest\Fixture\MagicConstantsClass;
use Roave\BetterReflectionTest\Fixture\MagicConstantsTrait;
use stdClass;

use function assert;
use function define;
use function is_string;
use function preg_quote;
use function realpath;
use function sprintf;
use function uniqid;

use const PHP_EOL;
use const PHP_INT_MAX;
use const PHP_VERSION_ID;

#[CoversClass(CompileNodeToValue::class)]
class CompileNodeToValueTest extends TestCase
{
    /**
     * @var \PhpParser\Parser
     */
    private $parser;

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

        $configuration       = BetterReflectionSingleton::instance();
        $this->parser        = $configuration->phpParser();
        $this->astLocator    = $configuration->astLocator();
        $this->sourceStubber = $configuration->sourceStubber();
    }

    /** @return Node\Stmt\Expression */
    private function parseCode(string $phpCode): Node\Stmt
    {
        $node = $this->parser->parse('<?php ' . $phpCode . ';')[0];
        assert($node instanceof Node\Stmt\Expression);

        return $node;
    }

    private function getDummyContext(): CompilerContext
    {
        $reflector       = new DefaultReflector(new AggregateSourceLocator([
            new StringSourceLocator('<?php class Foo {}', $this->astLocator),
            new PhpInternalSourceLocator($this->astLocator, $this->sourceStubber),
            new AutoloadSourceLocator($this->astLocator),
        ]));
        $classReflection = $reflector->reflectClass('Foo');

        return new CompilerContext($reflector, $classReflection);
    }

    private function getDummyContextWithGlobalNamespace(): CompilerContext
    {
        $reflector          = new DefaultReflector(new PhpInternalSourceLocator($this->astLocator, $this->sourceStubber));
        $constantReflection = $reflector->reflectConstant('PHP_VERSION_ID');

        return new CompilerContext(new DefaultReflector(new StringSourceLocator('<?php class EmptyClass {}', $this->astLocator)), $constantReflection);
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function nodeProvider(): array
    {
        return [
            ['1', 1],
            ['"hello"', 'hello'],
            ['null', null],
            ['1.1', 1.1],
            ['[]', []],
            ['false', false],
            ['true', true],
            ['[1,2,3]', [1, 2, 3]],
            ['["foo","bar"]', ['foo', 'bar']],
            ['[1 => "foo", 2 => "bar"]', [1 => 'foo', 2 => 'bar']],
            ['["foo" => "bar"]', ['foo' => 'bar']],
            ['-1', -1],
            ['-123.456', -123.456],
            ['2 * 3', 6],
            ['2 + 2 * 3', 8],
            ['2 + (2 * 3)', 8],
            ['(2 + 2) * 3', 12],
            ['5 - 2', 3],
            ['8 / 2', 4],
            ['["foo"."bar" => 2 * 3]', ['foobar' => 6]],
            ['true && false', false],
            ['false && true', false],
            ['false && false', false],
            ['true && true', true],
            ['true || false', true],
            ['false || true', true],
            ['false || false', false],
            ['true || true', true],
            ['0 & 2', 0],
            ['1 & 2', 0],
            ['2 & 2', 2],
            ['3 & 2', 2],
            ['4 & 2', 0],
            ['0 | 2', 2],
            ['1 | 2', 3],
            ['2 | 2', 2],
            ['3 | 2', 3],
            ['4 | 2', 6],
            ['0 ^ 2', 2],
            ['1 ^ 2', 3],
            ['2 ^ 2', 0],
            ['3 ^ 2', 1],
            ['4 ^ 2', 6],
            ['1 == 2', false],
            ['2 == 2', true],
            ['1 == "1"', true],
            ['1 > 2', false],
            ['2 > 2', false],
            ['3 > 2', true],
            ['1 >= 2', false],
            ['2 >= 2', true],
            ['3 >= 2', true],
            ['1 === 2', false],
            ['2 === 2', true],
            ['1 === "1"', false],
            ['true and false', false],
            ['false and true', false],
            ['false and false', false],
            ['true and true', true],
            ['true or false', true],
            ['false or true', true],
            ['false or false', false],
            ['true or true', true],
            ['true xor false', true],
            ['false xor true', true],
            ['false xor false', false],
            ['true xor true', false],
            ['2 % 2', 0],
            ['2 % 4', 2],
            ['1 != 2', true],
            ['2 != 2', false],
            ['1 != "1"', false],
            ['1 !== 2', true],
            ['2 !== 2', false],
            ['1 !== "1"', true],
            ['4 ** 3', 64],
            ['1 << 1', 2],
            ['1 << 2', 4],
            ['1 << 3', 8],
            ['2 >> 1', 1],
            ['4 >> 2', 1],
            ['8 >> 3', 1],
            ['1 < 2', true],
            ['2 < 2', false],
            ['3 < 2', false],
            ['1 <= 2', true],
            ['2 <= 2', true],
            ['3 <= 2', false],
            ['PHP_INT_MAX', PHP_INT_MAX],
            ['PHP_EOL', PHP_EOL],
            ['1 <=> 4', -1],
            ['4 <=> 1', 1],
            ['1 <=> 1', 0],
            ['5 ?? 4', 5],
        ];
    }

    /**
     * @param mixed $expectedValue
     */
    #[DataProvider('nodeProvider')]
    public function testVariousNodeCompilations(string $phpCode, $expectedValue): void
    {
        $node = $this->parseCode($phpCode);

        $compiledValue = (new CompileNodeToValue())->__invoke($node, $this->getDummyContext());

        self::assertSame($expectedValue, $compiledValue->value);
    }

    public function testResource(): void
    {
        $node = $this->parseCode('STDIN');

        $compiledValue = (new CompileNodeToValue())->__invoke($node, $this->getDummyContext());

        self::assertIsResource($compiledValue->value);
    }

    public function testConstantFunctionCall(): void
    {
        $node = new Node\Expr\FuncCall(new Node\Name('constant'), [new Node\Arg(new Node\Scalar\String_('PHP_VERSION_ID'))]);

        $compiledValue = (new CompileNodeToValue())->__invoke($node, $this->getDummyContext());

        self::assertSame(PHP_VERSION_ID, $compiledValue->value);
    }

    /** @return list<array{0: string}> */
    public static function dataTrueFalseNullShouldNotHaveConstantName(): array
    {
        return [
            ['true'],
            ['TruE'],
            ['false'],
            ['fAlSe'],
            ['null'],
            ['NULL'],
        ];
    }

    #[DataProvider('dataTrueFalseNullShouldNotHaveConstantName')]
    public function testTrueFalseNullShouldNotHaveConstantName(string $value): void
    {
        $node = $this->parseCode($value);

        $compiledValue = (new CompileNodeToValue())->__invoke($node, $this->getDummyContext());

        self::assertNull($compiledValue->constantName);
    }

    public function testExceptionThrownWhenInvalidNodeGiven(): void
    {
        $this->expectException(UnableToCompileNode::class);
        $this->expectExceptionMessageMatches(sprintf('#^Unable to compile expression in global namespace: unrecognized node type %s in file#', preg_quote(Yield_::class)));

        (new CompileNodeToValue())->__invoke(new Yield_(), $this->getDummyContextWithGlobalNamespace());
    }

    public function testExceptionThrownWhenUndefinedConstUsed(): void
    {
        $this->expectException(UnableToCompileNode::class);
        $this->expectExceptionMessageMatches('#^Could not locate constant "FOO" while evaluating expression in global namespace in file#');

        (new CompileNodeToValue())->__invoke(new ConstFetch(new Name('FOO')), $this->getDummyContextWithGlobalNamespace());
    }

    public function testExceptionThrownWhenUndefinedClassConstUsed(): void
    {
        $this->expectException(UnableToCompileNode::class);
        $this->expectExceptionMessageMatches('#^Could not locate constant EmptyClass::FOO while trying to evaluate constant expression in global namespace in file#');

        (new CompileNodeToValue())->__invoke(new Node\Expr\ClassConstFetch(new Name\FullyQualified('EmptyClass'), new Node\Identifier('FOO')), $this->getDummyContextWithGlobalNamespace());
    }

    public function testConstantValueCompiled(): void
    {
        $constName = uniqid('BETTER_REFLECTION_TEST_CONST_', true);
        define($constName, 123);

        self::assertSame(123, (new CompileNodeToValue())->__invoke(new ConstFetch(new Name($constName)), $this->getDummyContext())->value);
    }

    public function testConstantResolutionWithAnotherConstant(): void
    {
        $phpCode = <<<'PHP'
<?php
namespace Bar;

const SECOND = 1;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;
const WEEK = 7 * DAY;
PHP;

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));

        self::assertSame(1, $reflector->reflectConstant('Bar\SECOND')->getValue());
        self::assertSame(60, $reflector->reflectConstant('Bar\MINUTE')->getValue());
        self::assertSame(3600, $reflector->reflectConstant('Bar\HOUR')->getValue());
        self::assertSame(86400, $reflector->reflectConstant('Bar\DAY')->getValue());
        self::assertSame(604800, $reflector->reflectConstant('Bar\WEEK')->getValue());
    }

    public function testGlobalConstantResolutionInNamespace(): void
    {
        $phpCode = <<<'PHP'
<?php
namespace Bar;

class Foo {
    public function method($param = PHP_VERSION_ID) {}
}
PHP;

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Bar\Foo');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame(PHP_VERSION_ID, $paramInfo->getDefaultValue());
        self::assertTrue($paramInfo->isDefaultValueConstant());
        self::assertSame('PHP_VERSION_ID', $paramInfo->getDefaultValueConstantName());
    }

    public function testUnqualifiedConstantResolution(): void
    {
        $phpCode = <<<'PHP'
<?php
namespace Bar;

const SOME_CONSTANT = 'constant';

class Foo {
    public function method($param = SOME_CONSTANT) {}
}
PHP;

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Bar\Foo');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame('constant', $paramInfo->getDefaultValue());
        self::assertTrue($paramInfo->isDefaultValueConstant());
        self::assertSame('Bar\SOME_CONSTANT', $paramInfo->getDefaultValueConstantName());
    }

    public function testFullyQualifiedConstantResolution(): void
    {
        $phpCode = <<<'PHP'
<?php
namespace Bar;

const SOME_CONSTANT = 'constant';

class Foo {
    public function method($param = \Bar\SOME_CONSTANT) {}
}
PHP;

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Bar\Foo');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame('constant', $paramInfo->getDefaultValue());
        self::assertTrue($paramInfo->isDefaultValueConstant());
        self::assertSame('Bar\SOME_CONSTANT', $paramInfo->getDefaultValueConstantName());
    }

    public function testClassConstantResolutionSelfForMethod(): void
    {
        $phpCode = '<?php
        class Foo {
            const BAR = "baz";
            public function method($param = self::BAR) {}
        }
        ';

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Foo');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame('baz', $paramInfo->getDefaultValue());
    }

    public function testClassConstantResolutionWithAnotherClassConstant(): void
    {
        $phpCode = <<<'PHP'
<?php
namespace Bar;

class Foo {
    const SECOND = 1;
    const MINUTE = 60 * self::SECOND;
    const HOUR = 60 * self::MINUTE;
    const DAY = 24 * self::HOUR;
    const WEEK = 7 * self::DAY;
}
PHP;

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Foo');

        self::assertSame(1, $classInfo->getConstant('SECOND')->getValue());
        self::assertSame(60, $classInfo->getConstant('MINUTE')->getValue());
        self::assertSame(3600, $classInfo->getConstant('HOUR')->getValue());
        self::assertSame(86400, $classInfo->getConstant('DAY')->getValue());
        self::assertSame(604800, $classInfo->getConstant('WEEK')->getValue());
    }

    public function testClassConstantResolutionExternalForMethod(): void
    {
        $phpCode = '<?php
        class Foo {
            const BAR = "baz";
        }
        class Bat {
            const QUX = "quux";
            public function method($param = Foo::BAR) {}
        }
        ';

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Bat');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame('baz', $paramInfo->getDefaultValue());
    }

    public function testClassConstantResolutionStaticForMethod(): void
    {
        $phpCode = '<?php
        class Foo {
            const BAR = "baz";
            public function method($param = static::BAR) {}
        }
        ';

        $reflector  = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo  = $reflector->reflectClass('Foo');
        $methodInfo = $classInfo->getMethod('method');
        $paramInfo  = $methodInfo->getParameter('param');

        self::assertSame('baz', $paramInfo->getDefaultValue());
    }

    public function testClassConstantClassNameResolution(): void
    {
        $phpCode = '<?php

        class Foo {
        }
        class Bat {
            const QUX = Foo::class;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bat');
        self::assertSame('Foo', ($getConstant = $classInfo->getConstant('QUX')) ? $getConstant->getValue() : null);
    }

    public function testClassConstantClassNameNamespaceResolution(): void
    {
        $phpCode = '<?php
        namespace Bar;

        class Foo {
        }
        class Bat {
            const QUX = Foo::class;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Bat');
        self::assertSame('Bar\Foo', ($getConstant = $classInfo->getConstant('QUX')) ? $getConstant->getValue() : null);
    }

    public function testClassConstantClassNameOutOfScopeResolution(): void
    {
        $phpCode = '<?php
        namespace Bar;

        use My\Awesome\Foo;

        class Bat {
            const QUX = Foo::class;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Bat');
        self::assertSame('My\Awesome\Foo', ($getConstant = $classInfo->getConstant('QUX')) ? $getConstant->getValue() : null);
    }

    public function testClassConstantClassNameAliasedResolution(): void
    {
        $phpCode = '<?php
        namespace Bar;

        use My\Awesome\Foo as FooAlias;

        class Bat {
            const QUX = FooAlias::class;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Bat');
        self::assertSame('My\Awesome\Foo', ($getConstant = $classInfo->getConstant('QUX')) ? $getConstant->getValue() : null);
    }

    public function testClassConstantResolutionFromParent(): void
    {
        $phpCode = '<?php
        namespace Bar;

        class Foo {
            const BAR = "baz";
        }
        class Bat extends Foo {
            private $property = self::BAR;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Bat');
        self::assertSame('baz', $classInfo->getProperty('property')->getDefaultValue());
    }

    public function testClassConstantResolutionFromParentParent(): void
    {
        $phpCode = '<?php
        namespace Bar;

        class Foo {
            const BAR = "baz";
        }
        class Bar extends Foo {}
        class Bat extends Bar {
            private $property = self::BAR;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar\Bat');
        self::assertSame('baz', $classInfo->getProperty('property')->getDefaultValue());
    }

    public function testDifferentClassConstantAsDefaultValueWhenInNamespace(): void
    {
        $phpCode = '<?php
        namespace Foo;

        class Foo {
            const BAR = "baz";
        }

        class Bar {
            private $property = Foo::BAR;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Foo\Bar');
        self::assertSame('baz', $classInfo->getProperty('property')->getDefaultValue());
    }

    public function testDifferentClassConstantAsDefaultValueWhenNotInNamespace(): void
    {
        $phpCode = '<?php
        class Foo {
            const BAR = "baz";
        }

        class Bar {
            private $property = Foo::BAR;
        }
        ';

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bar');
        self::assertSame('baz', $classInfo->getProperty('property')->getDefaultValue());
    }

    public function testSelfStaticOrParentAsPropertyDefaultValue(): void
    {
        $phpCode = <<<'PHP'
        <?php

        class Baz {
            const PARENT_CONSTANT = 'parentConstant';
        }

        class Foo extends Baz {
            const SELF_CONSTANT = 'selfConstant';
            const STATIC_CONSTANT = 'staticConstant';
            const PARENT_CONSTANT = 'selfConstant';

            public $selfClass = self::class;
            public $staticClass = static::class;
            public $parentClass = parent::class;

            public $selfConstant = self::SELF_CONSTANT;
            public $staticConstant = self::STATIC_CONSTANT;
            public $parentConstant = parent::PARENT_CONSTANT;
        }
PHP;

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Foo');

        self::assertSame('Foo', $classInfo->getProperty('selfClass')->getDefaultValue());
        self::assertSame('Foo', $classInfo->getProperty('staticClass')->getDefaultValue());
        self::assertSame('Baz', $classInfo->getProperty('parentClass')->getDefaultValue());

        self::assertSame('selfConstant', $classInfo->getProperty('selfConstant')->getDefaultValue());
        self::assertSame('staticConstant', $classInfo->getProperty('staticConstant')->getDefaultValue());
        self::assertSame('parentConstant', $classInfo->getProperty('parentConstant')->getDefaultValue());
    }

    /** @return list<array{0: string, 1: string|int}> */
    public static function enumCasePropertyProvider(): array
    {
        return [
            ['name', 'ONE'],
            ['value', 1],
        ];
    }

    /**
     * @param string|int $expectedPropertyValue
     */
    #[DataProvider('enumCasePropertyProvider')]
    public function testEnumPropertyValue(string $propertyName, $expectedPropertyValue): void
    {
        $phpCode = sprintf(<<<'PHP'
            <?php

            enum Foo: int {
                case ONE = 1;
            }
            class Bat {
                const ONE_VALUE = Foo::ONE->%s;
            }
            PHP, $propertyName);

        $reflector = new DefaultReflector(new AggregateSourceLocator([
            new StringSourceLocator($phpCode, $this->astLocator),
            new PhpInternalSourceLocator($this->astLocator, $this->sourceStubber),
        ]));
        $classInfo = $reflector->reflectClass('Bat');
        self::assertSame($expectedPropertyValue, ($getConstant = $classInfo->getConstant('ONE_VALUE')) ? $getConstant->getValue() : null);
    }

    public function testEnumPropertyValueThrowsExceptionWhenNoEnum(): void
    {
        $phpCode = <<<'PHP'
        <?php

        class Foo {
            const ONE = 1;
        }
        class Bat {
            const ONE_VALUE = Foo::ONE->value;
        }
        PHP;

        $reflector = new DefaultReflector(new StringSourceLocator($phpCode, $this->astLocator));
        $classInfo = $reflector->reflectClass('Bat');

        $this->expectException(UnableToCompileNode::class);
        $classInfo->getConstant('ONE_VALUE')->getValue();
    }

    public function testEnumPropertyValueThrowsExceptionWhenCaseDoesNotExist(): void
    {
        $phpCode = <<<'PHP'
        <?php

        enum Foo: int {
            case ONE = 1;
        }
        class Bat {
            const TWO_VALUE = Foo::TWO->value;
        }
        PHP;

        $reflector = new DefaultReflector(new AggregateSourceLocator([
            new StringSourceLocator($phpCode, $this->astLocator),
            new PhpInternalSourceLocator($this->astLocator, $this->sourceStubber),
        ]));
        $classInfo = $reflector->reflectClass('Bat');

        $this->expectException(UnableToCompileNode::class);
        $classInfo->getConstant('TWO_VALUE')->getValue();
    }

    public function testEnumPropertyValueThrowsExceptionWhenPropertyDoesNotExist(): void
    {
        $phpCode = <<<'PHP'
        <?php

        enum Foo: int {
            case ONE = 1;
        }
        class Bat {
            const ONE_VALUE = Foo::ONE->missing;
        }
        PHP;

        $reflector = new DefaultReflector(new AggregateSourceLocator([
            new StringSourceLocator($phpCode, $this->astLocator),
            new PhpInternalSourceLocator($this->astLocator, $this->sourceStubber),
        ]));
        $classInfo = $reflector->reflectClass('Bat');

        $this->expectException(UnableToCompileNode::class);
        $classInfo->getConstant('ONE_VALUE')->getValue();
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function magicConstantsWithoutNamespaceProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['_DIR', $dir],
            ['_FILE', $dir . '/MagicConstants.php'],
            ['_LINE', 7],
            ['_NAMESPACE', ''],
            ['_CLASS', ''],
            ['_TRAIT', ''],
            ['_METHOD', ''],
            ['_FUNCTION', ''],
        ];
    }

    /**
     * @param mixed $expectedValue
     */
    #[DataProvider('magicConstantsWithoutNamespaceProvider')]
    public function testMagicConstantsWithoutNamespace(string $constantName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $constant  = $reflector->reflectConstant($constantName);

        self::assertSame($expectedValue, $constant->getValue());
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function magicConstantsInNamespaceProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['_DIR', $dir],
            ['_FILE', $dir . '/MagicConstants.php'],
            ['_LINE', 20],
            ['_NAMESPACE', 'Roave\BetterReflectionTest\Fixture'],
            ['_CLASS', ''],
            ['_TRAIT', ''],
            ['_METHOD', ''],
            ['_FUNCTION', ''],
        ];
    }

    /**
     * @param mixed $expectedValue
     */
    #[DataProvider('magicConstantsInNamespaceProvider')]
    public function testMagicConstantsInNamespace(string $constantName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $constant  = $reflector->reflectConstant('Roave\BetterReflectionTest\Fixture\\' . $constantName);

        self::assertSame($expectedValue, $constant->getValue());
    }

    /** @return list<array{0: non-empty-string, 1: mixed}> */
    public static function magicConstantsInTraitProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['dir', $dir],
            ['file', $dir . '/MagicConstants.php'],
            ['line', 31],
            ['namespace', 'Roave\BetterReflectionTest\Fixture'],
            ['class', 'Roave\BetterReflectionTest\Fixture\MagicConstantsTrait'],
            ['trait', 'Roave\BetterReflectionTest\Fixture\MagicConstantsTrait'],
            ['method', ''],
            ['function', ''],
        ];
    }

    /** @param non-empty-string $propertyName
     * @param mixed $expectedValue */
    #[DataProvider('magicConstantsInTraitProvider')]
    public function testMagicConstantsInTrait(string $propertyName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $class     = $reflector->reflectClass(MagicConstantsTrait::class);
        $property  = $class->getProperty($propertyName);

        self::assertSame($expectedValue, $property->getDefaultValue());
    }

    /** @return list<array{0: non-empty-string, 1: mixed}> */
    public static function magicConstantsInClassProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['dir', $dir],
            ['file', $dir . '/MagicConstants.php'],
            ['line', 43],
            ['namespace', 'Roave\BetterReflectionTest\Fixture'],
            ['class', 'Roave\BetterReflectionTest\Fixture\MagicConstantsClass'],
            ['trait', ''],
            ['method', ''],
            ['function', ''],
        ];
    }

    /** @param non-empty-string $propertyName
     * @param mixed $expectedValue */
    #[DataProvider('magicConstantsInClassProvider')]
    public function testMagicConstantsInClass(string $propertyName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $class     = $reflector->reflectClass(MagicConstantsClass::class);
        $property  = $class->getProperty($propertyName);

        self::assertSame($expectedValue, $property->getDefaultValue());
    }

    /** @return list<array{0: non-empty-string, 1: mixed}> */
    public static function magicConstantsInMethodProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['dir', $dir],
            ['file', $dir . '/MagicConstants.php'],
            ['line', 53],
            ['namespace', 'Roave\BetterReflectionTest\Fixture'],
            ['class', 'Roave\BetterReflectionTest\Fixture\MagicConstantsClass'],
            ['trait', ''],
            ['method', 'Roave\BetterReflectionTest\Fixture\MagicConstantsClass::magicConstantsMethod'],
            ['function', 'magicConstantsMethod'],
        ];
    }

    /** @param non-empty-string $parameterName
     * @param mixed $expectedValue */
    #[DataProvider('magicConstantsInMethodProvider')]
    public function testMagicConstantsInMethod(string $parameterName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $class     = $reflector->reflectClass(MagicConstantsClass::class);
        $method    = $class->getMethod('magicConstantsMethod');
        $parameter = $method->getParameter($parameterName);

        self::assertSame($expectedValue, $parameter->getDefaultValue());
    }

    /** @return list<array{0: non-empty-string, 1: mixed}> */
    public static function magicConstantsInFunctionProvider(): array
    {
        $dir = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture'));

        return [
            ['dir', $dir],
            ['file', $dir . '/MagicConstants.php'],
            ['line', 67],
            ['namespace', 'Roave\BetterReflectionTest\Fixture'],
            ['class', ''],
            ['trait', ''],
            ['method', 'Roave\BetterReflectionTest\Fixture\magicConstantsFunction'],
            ['function', 'Roave\BetterReflectionTest\Fixture\magicConstantsFunction'],
        ];
    }

    /** @param non-empty-string $parameterName
     * @param mixed $expectedValue */
    #[DataProvider('magicConstantsInFunctionProvider')]
    public function testMagicConstantsInFunction(string $parameterName, $expectedValue): void
    {
        $reflector = new DefaultReflector(new SingleFileSourceLocator(self::realPath(__DIR__ . '/../Fixture/MagicConstants.php'), $this->astLocator));
        $function  = $reflector->reflectFunction('Roave\BetterReflectionTest\Fixture\magicConstantsFunction');
        $parameter = $function->getParameter($parameterName);

        self::assertSame($expectedValue, $parameter->getDefaultValue());
    }

    /** @return list<array{0: non-empty-string}> */
    public static function fileAndDirectoryMagicConstantsWithoutFileNameProvider(): array
    {
        return [
            ['file'],
            ['dir'],
        ];
    }

    /** @param non-empty-string $parameterName */
    #[DataProvider('fileAndDirectoryMagicConstantsWithoutFileNameProvider')]
    public function testFileAndDirectoryMagicConstantsWithoutFileName(string $parameterName): void
    {
        $php = '<?php function functionWithMagicConstants($file = __FILE__, $dir = __DIR__) {}';

        $reflector = new DefaultReflector(new StringSourceLocator($php, $this->astLocator));
        $function  = $reflector->reflectFunction('functionWithMagicConstants');
        $parameter = $function->getParameter($parameterName);

        $this->expectException(UnableToCompileNode::class);
        $this->expectExceptionMessage('No file name for function functionWithMagicConstants() (line 1)');
        $parameter->getDefaultValue();
    }

    public function testNewInInitializers(): void
    {
        $file = FileHelper::normalizeWindowsPath(self::realPath(__DIR__ . '/../Fixture/NewInInitializers.php'));

        $reflector = new DefaultReflector(new SingleFileSourceLocator($file, $this->astLocator));
        $class     = $reflector->reflectClass(ClassWithNewInInitializers::class);
        $method    = $class->getMethod('methodWithInitializer');
        $parameter = $method->getParameter('parameterWithInitializer');

        $value = $parameter->getDefaultValue();

        self::assertInstanceOf(ArrayObject::class, $value);
        self::assertSame(6, $value->count());

        self::assertSame('a', $value[0]);
        self::assertSame('b', $value[1]);
        self::assertSame('constant', $value[2]);
        self::assertSame(PHP_VERSION_ID, $value[3]);
        self::assertSame(ClassWithNewInInitializers::class, $value[4]);
        self::assertInstanceOf(stdClass::class, $value[5]);
    }

    /** @return non-empty-string
     * @param string|true $path */
    private static function realPath($path): string
    {
        $realPath = realpath($path);

        assert(is_string($realPath) && $realPath !== '');

        return $realPath;
    }
}
