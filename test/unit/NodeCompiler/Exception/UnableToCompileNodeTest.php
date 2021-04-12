<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\NodeCompiler\Exception;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\BetterReflection\NodeCompiler\CompilerContext;
use PHPStan\BetterReflection\NodeCompiler\Exception\UnableToCompileNode;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflector\ClassReflector;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use PHPUnit\Framework\TestCase;
use Roave\BetterReflectionTest\BetterReflectionSingleton;
use function sprintf;

/**
 * @covers \PHPStan\BetterReflection\NodeCompiler\Exception\UnableToCompileNode
 */
final class UnableToCompileNodeTest extends TestCase
{
    public function testDefaults() : void
    {
        $exception = new UnableToCompileNode();

        self::assertNull($exception->constantName());
    }

    /** @dataProvider supportedContextTypes */
    public function testBecauseOfNotFoundConstantReference(CompilerContext $context) : void
    {
        $constantName = 'FOO';

        $exception = UnableToCompileNode::becauseOfNotFoundConstantReference(
            $context,
            new ConstFetch(new Name($constantName))
        );

        $contextName = $context->hasSelf() ? 'EmptyClass' : 'unknown context (probably a function)';

        self::assertSame(
            sprintf(
                'Could not locate constant "%s" while evaluating expression in %s at line -1',
                $constantName,
                $contextName
            ),
            $exception->getMessage()
        );

        self::assertSame($constantName, $exception->constantName());
    }

    /** @dataProvider supportedContextTypes */
    public function testBecauseOfNotFoundClassConstantReference(CompilerContext $context) : void
    {
        $contextName = $context->hasSelf() ? 'EmptyClass' : 'unknown context (probably a function)';

        $targetClass = $this->createMock(ReflectionClass::class);

        $targetClass
            ->expects(self::any())
            ->method('getName')
            ->willReturn('An\\Example');

        self::assertSame(
            'Could not locate constant An\Example::SOME_CONSTANT while trying to evaluate constant expression in '
            . $contextName . ' at line -1',
            UnableToCompileNode::becauseOfNotFoundClassConstantReference(
                $context,
                $targetClass,
                new ClassConstFetch(
                    new Name\FullyQualified('A'),
                    new Identifier('SOME_CONSTANT')
                )
            )->getMessage()
        );
    }

    /** @dataProvider supportedContextTypes */
    public function testForUnRecognizedExpressionInContext(CompilerContext $context) : void
    {
        $contextName = $context->hasSelf() ? 'EmptyClass' : 'unknown context (probably a function)';

        self::assertSame(
            'Unable to compile expression in ' . $contextName
            . ': unrecognized node type ' . Yield_::class
            . ' at line -1',
            UnableToCompileNode::forUnRecognizedExpressionInContext(
                new Yield_(new String_('')),
                $context
            )->getMessage()
        );
    }

    /** @return CompilerContext[] */
    public function supportedContextTypes() : array
    {
        $reflector = new ClassReflector(new StringSourceLocator(
            '<?php class EmptyClass {}',
            BetterReflectionSingleton::instance()->astLocator()
        ));

        return [
            [new CompilerContext($reflector, null, null, null, null)],
            [new CompilerContext($reflector, null, $reflector->reflect('EmptyClass'), null, null)],
        ];
    }
}
