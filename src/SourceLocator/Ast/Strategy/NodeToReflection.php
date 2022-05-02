<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\Ast\Strategy;

use PhpParser\Node;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionConstant;
use Roave\BetterReflection\Reflection\ReflectionEnum;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Located\LocatedSource;

/**
 * @internal
 */
class NodeToReflection implements AstConversionStrategy
{
    /**
     * Take an AST node in some located source (potentially in a namespace) and
     * convert it to a Reflection
     * @param \PhpParser\Node\Expr\ArrowFunction|\PhpParser\Node\Expr\Closure|\PhpParser\Node\Expr\FuncCall|\PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Const_|\PhpParser\Node\Stmt\Enum_|\PhpParser\Node\Stmt\Function_|\PhpParser\Node\Stmt\Interface_|\PhpParser\Node\Stmt\Trait_ $node
     * @return \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionConstant|\Roave\BetterReflection\Reflection\ReflectionFunction
     */
    public function __invoke(Reflector $reflector, $node, LocatedSource $locatedSource, ?Node\Stmt\Namespace_ $namespace, ?int $positionInNode = null)
    {
        if ($node instanceof Node\Stmt\Enum_) {
            return ReflectionEnum::createFromNode($reflector, $node, $locatedSource, $namespace);
        }
        if ($node instanceof Node\Stmt\ClassLike) {
            return ReflectionClass::createFromNode($reflector, $node, $locatedSource, $namespace);
        }
        if (
            $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Expr\ArrowFunction
        ) {
            return ReflectionFunction::createFromNode($reflector, $node, $locatedSource, $namespace);
        }
        if ($node instanceof Node\Stmt\Const_) {
            return ReflectionConstant::createFromNode($reflector, $node, $locatedSource, $namespace, $positionInNode);
        }
        return ReflectionConstant::createFromNode($reflector, $node, $locatedSource);
    }
}
