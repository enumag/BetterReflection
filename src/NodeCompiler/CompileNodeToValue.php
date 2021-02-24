<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\NodeCompiler;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflection\ReflectionClassConstant;
use PHPStan\BetterReflection\Reflection\ReflectionConstant;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Util\FileHelper;
use function assert;
use function constant;
use function defined;
use function dirname;
use function realpath;
use function sprintf;
use function strtolower;

class CompileNodeToValue
{
    /**
     * Compile an expression from a node into a value.
     *
     * @param Node\Stmt\Expression|Node\Expr $node Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     *
     * @return scalar|array<scalar>|null
     *
     * @throws Exception\UnableToCompileNode
     */
    public function __invoke(Node $node, CompilerContext $context)
    {
        if ($node instanceof Node\Stmt\Expression) {
            return $this($node->expr, $context);
        }

        $constExprEvaluator = new ConstExprEvaluator(function (Node\Expr $node) use ($context) {
            if ($node instanceof Node\Expr\ConstFetch) {
                return $this->compileConstFetch($node, $context);
            }

            if ($node instanceof Node\Expr\ClassConstFetch) {
                return $this->compileClassConstFetch($node, $context);
            }

            if ($node instanceof Node\Scalar\MagicConst\Dir) {
                return $this->compileDirConstant($context);
            }

            if ($node instanceof Node\Scalar\MagicConst\Class_) {
                return $this->compileClassConstant($context);
            }

            if ($node instanceof Node\Scalar\MagicConst\File) {
                return $context->getFileName();
            }

            if ($node instanceof Node\Scalar\MagicConst\Line) {
                return $node->getLine();
            }

            if ($node instanceof Node\Scalar\MagicConst\Namespace_) {
                return $context->getNamespace() ?? '';
            }

            if ($node instanceof Node\Scalar\MagicConst\Method) {
                if ($context->hasSelf()) {
                    if ($context->getFunctionName() !== null) {
                        return sprintf('%s::%s', $context->getSelf()->getName(), $context->getFunctionName());
                    }

                    return '';
                }

                if ($context->getFunctionName() !== null) {
                    return $context->getFunctionName();
                }

                return '';
            }

            if ($node instanceof Node\Scalar\MagicConst\Function_) {
                if ($context->getFunctionName() !== null) {
                    return $context->getFunctionName();
                }

                return '';
            }

            if ($node instanceof Node\Scalar\MagicConst\Trait_) {
                if ($context->hasSelf()) {
                    $class = $context->getSelf();
                    if ($class->isTrait()) {
                        return $class->getName();
                    }
                }

                return '';
            }

            throw Exception\UnableToCompileNode::forUnRecognizedExpressionInContext($node, $context);
        });

        return $constExprEvaluator->evaluateDirectly($node);
    }

    /**
     * Compile constant expressions
     *
     * @return scalar|array<scalar>|null
     *
     * @throws Exception\UnableToCompileNode
     */
    private function compileConstFetch(Node\Expr\ConstFetch $constNode, CompilerContext $context)
    {
        $constantName = $constNode->name->toString();
        switch (strtolower($constantName)) {
            case 'null':
                return null;
            case 'false':
                return false;
            case 'true':
                return true;
            default:
                if ($context->getNamespace() !== null && ! $constNode->name->isFullyQualified()) {
                    $namespacedName = sprintf('%s\\%s', $context->getNamespace(), $constantName);
                    if (defined($namespacedName)) {
                        return constant($namespacedName);
                    }

                    try {
                        return ReflectionConstant::createFromName($namespacedName)->getValue();
                    } catch (IdentifierNotFound $e) {
                        // pass
                    }
                }

                if (defined($constantName)) {
                    return constant($constantName);
                }

                try {
                    return ReflectionConstant::createFromName($constantName)->getValue();
                } catch (IdentifierNotFound $e) {
                    throw Exception\UnableToCompileNode::becauseOfNotFoundConstantReference($context, $constNode);
                }
        }
    }

    /**
     * Compile class constants
     *
     * @return scalar|array<scalar>|null
     *
     * @throws IdentifierNotFound
     * @throws Exception\UnableToCompileNode If a referenced constant could not be located on the expected referenced class.
     */
    private function compileClassConstFetch(Node\Expr\ClassConstFetch $node, CompilerContext $context)
    {
        assert($node->name instanceof Node\Identifier);
        $nodeName = $node->name->name;
        assert($node->class instanceof Node\Name);
        $className = $node->class->toString();

        if ($nodeName === 'class') {
            return $this->resolveClassNameForClassNameConstant($className, $context);
        }

        $classInfo = null;

        if ($className === 'self' || $className === 'static') {
            $classInfo = $context->getSelf()->hasConstant($nodeName) ? $context->getSelf() : null;
        } elseif ($className === 'parent') {
            $classInfo = $context->getSelf()->getParentClass();
        }

        if ($classInfo === null) {
            $classInfo = $context->getReflector()->reflect($className);
            assert($classInfo instanceof ReflectionClass);
        }

        $reflectionConstant = $classInfo->getReflectionConstant($nodeName);

        if (! $reflectionConstant instanceof ReflectionClassConstant) {
            throw Exception\UnableToCompileNode::becauseOfNotFoundClassConstantReference($context, $classInfo, $node);
        }

        return $this->__invoke(
            $reflectionConstant->getAst()->consts[$reflectionConstant->getPositionInAst()]->value,
            new CompilerContext($context->getReflector(), $context->hasFileName() ? $context->getFileName() : null, $classInfo, $context->getNamespace(), $context->getFunctionName())
        );
    }

    /**
     * Compile a __DIR__ node
     */
    private function compileDirConstant(CompilerContext $context) : string
    {
        return FileHelper::normalizeWindowsPath(dirname(realpath($context->getFileName())));
    }

    /**
     * Compiles magic constant __CLASS__
     */
    private function compileClassConstant(CompilerContext $context) : string
    {
        return $context->hasSelf() ? $context->getSelf()->getName() : '';
    }

    private function resolveClassNameForClassNameConstant(string $className, CompilerContext $context) : string
    {
        if ($className === 'self' || $className === 'static') {
            return $context->getSelf()->getName();
        }

        if ($className === 'parent') {
            $parentClass = $context->getSelf()->getParentClass();
            assert($parentClass instanceof ReflectionClass);

            return $parentClass->getName();
        }

        return $className;
    }
}
