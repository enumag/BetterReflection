<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use PhpParser\Node;
use PhpParser\Node\Expr\Yield_ as YieldNode;
use PhpParser\Node\Expr\YieldFrom as YieldFromNode;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use Roave\BetterReflection\Reflection\Annotation\AnnotationHelper;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\SourceLocator\Located\LocatedSource;
use Roave\BetterReflection\Util\CalculateReflectionColumn;
use Roave\BetterReflection\Util\Exception\NoNodePosition;
use Roave\BetterReflection\Util\GetLastDocComment;

use function array_filter;
use function assert;
use function count;
use function is_array;

trait ReflectionFunctionAbstract
{
    /**
     * @var string|null
     */
    private $cachedName;

    /**
     * @var string
     */
    private $namespaceName;

    /**
     * @var bool
     */
    private $inNamespace;

    /** @var list<ReflectionParameter> */
    private $parameters;

    /**
     * @var string
     */
    private $docComment;

    /**
     * @var bool
     */
    private $isClosure;

    /**
     * @var bool
     */
    private $isGenerator;

    /**
     * @var int
     */
    private $startLine;

    /**
     * @var int
     */
    private $endLine;

    /**
     * @var int
     */
    private $startColumn;

    /**
     * @var int
     */
    private $endColumn;

    /**
     * @var bool
     */
    private $byRef;

    /**
     * @var \PhpParser\Node\ComplexType|\PhpParser\Node\Identifier|\PhpParser\Node\Name|null
     */
    private $astReturnType;

    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    private $returnType;

    /**
     * @param \PhpParser\Node\Expr\ArrowFunction|\PhpParser\Node\Expr\Closure|\PhpParser\Node\Stmt\ClassMethod|\PhpParser\Node\Stmt\Function_ $node
     */
    protected function populateTrait($node, ?NamespaceNode $declaringNamespace): void
    {
        $this->namespaceName = (($name = ($declaringNamespace2 = $declaringNamespace) ? $declaringNamespace2->name : null) ? $name->toString() : null) ?? '';
        $this->inNamespace = $declaringNamespace !== null
            && $declaringNamespace->name !== null;
        $this->parameters = $this->getParametersInternal($node);
        $this->docComment = GetLastDocComment::forNode($node);
        $this->isClosure = $node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction;
        $this->isGenerator = $this->nodeIsOrContainsYield($node);
        $this->startLine = $node->getStartLine();
        $this->endLine = $node->getEndLine();
        try {
            $this->startColumn = CalculateReflectionColumn::getStartColumn($this->locatedSource->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->startColumn = -1;
        }
        try {
            $this->endColumn = CalculateReflectionColumn::getEndColumn($this->locatedSource->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->endColumn = -1;
        }
        $this->byRef = $node->byRef;
        $this->astReturnType = $node->getReturnType();
        $this->returnType = $this->createReturnType($node->getReturnType());
    }

    abstract public function __toString(): string;

    abstract public function getShortName(): string;

    /**
     * Get the "full" name of the function (e.g. for A\B\foo, this will return
     * "A\B\foo").
     */
    public function getName(): string
    {
        if ($this->cachedName !== null) {
            return $this->cachedName;
        }

        if (! $this->inNamespace()) {
            return $this->cachedName = $this->getShortName();
        }

        return $this->cachedName = $this->getNamespaceName() . '\\' . $this->getShortName();
    }

    /**
     * Get the "namespace" name of the function (e.g. for A\B\foo, this will
     * return "A\B").
     */
    public function getNamespaceName(): string
    {
        return $this->namespaceName;
    }

    /**
     * Decide if this function is part of a namespace. Returns false if the class
     * is in the global namespace or does not have a specified namespace.
     */
    public function inNamespace(): bool
    {
        return $this->inNamespace;
    }

    /**
     * Get the number of parameters for this class.
     */
    public function getNumberOfParameters(): int
    {
        return count($this->getParameters());
    }

    /**
     * Get the number of required parameters for this method.
     */
    public function getNumberOfRequiredParameters(): int
    {
        return count(array_filter($this->getParameters(), static function (ReflectionParameter $p) : bool {
            return ! $p->isOptional();
        }));
    }

    /**
     * Get an array list of the parameters for this method signature, as an
     * array of ReflectionParameter instances.
     *
     * @return list<ReflectionParameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<ReflectionParameter>
     * @param \PhpParser\Node\Expr\ArrowFunction|\PhpParser\Node\Expr\Closure|\PhpParser\Node\Stmt\ClassMethod|\PhpParser\Node\Stmt\Function_ $node
     */
    private function getParametersInternal($node): array
    {
        $parameters = [];

        /** @var list<Node\Param> $nodeParams */
        $nodeParams = $node->params;
        foreach ($nodeParams as $paramIndex => $paramNode) {
            $parameters[] = ReflectionParameter::createFromNode($this->reflector, $paramNode, $this, $paramIndex, $this->isParameterOptional($nodeParams, $paramNode, $paramIndex));
        }

        return $parameters;
    }

    /**
     * @param list<Node\Param> $nodeParams
     */
    private function isParameterOptional(array $nodeParams, Node\Param $paramNode, int $paramIndex): bool
    {
        if ($paramNode->variadic) {
            return true;
        }

        if ($paramNode->default === null) {
            return false;
        }

        foreach ($nodeParams as $otherParameterIndex => $otherParameterNode) {
            if ($otherParameterIndex <= $paramIndex) {
                continue;
            }

            // When we find next parameter that does not have a default or is not variadic,
            // it means current parameter cannot be optional EVEN if it has a default value
            if ($otherParameterNode->default === null && ! $otherParameterNode->variadic) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a single parameter by name. Returns null if parameter not found for
     * the function.
     */
    public function getParameter(string $parameterName): ?ReflectionParameter
    {
        foreach ($this->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return $parameter;
            }
        }

        return null;
    }

    public function getDocComment(): string
    {
        return $this->docComment;
    }

    public function getFileName(): ?string
    {
        return $this->locatedSource->getFileName();
    }

    public function getLocatedSource(): LocatedSource
    {
        return $this->locatedSource;
    }

    /**
     * Is this function a closure?
     */
    public function isClosure(): bool
    {
        return $this->isClosure;
    }

    public function isDeprecated(): bool
    {
        return AnnotationHelper::isDeprecated($this->getDocComment());
    }

    public function isInternal(): bool
    {
        return $this->locatedSource->isInternal();
    }

    /**
     * Is this a user-defined function (will always return the opposite of
     * whatever isInternal returns).
     */
    public function isUserDefined(): bool
    {
        return ! $this->isInternal();
    }

    public function getExtensionName(): ?string
    {
        return $this->locatedSource->getExtensionName();
    }

    /**
     * Check if the function has a variadic parameter.
     */
    public function isVariadic(): bool
    {
        $parameters = $this->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively search an array of statements (PhpParser nodes) to find if a
     * yield expression exists anywhere (thus indicating this is a generator).
     */
    private function nodeIsOrContainsYield(Node $node): bool
    {
        if ($node instanceof YieldNode) {
            return true;
        }

        if ($node instanceof YieldFromNode) {
            return true;
        }

        /** @psalm-var string $nodeName */
        foreach ($node->getSubNodeNames() as $nodeName) {
            $nodeProperty = $node->$nodeName;

            if ($nodeProperty instanceof Node && $this->nodeIsOrContainsYield($nodeProperty)) {
                return true;
            }

            if (! is_array($nodeProperty)) {
                continue;
            }

            /** @psalm-var mixed $nodePropertyArrayItem */
            foreach ($nodeProperty as $nodePropertyArrayItem) {
                if ($nodePropertyArrayItem instanceof Node && $this->nodeIsOrContainsYield($nodePropertyArrayItem)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this function can be used as a generator (i.e. contains the
     * "yield" keyword).
     */
    public function isGenerator(): bool
    {
        return $this->isGenerator;
    }

    /**
     * Get the line number that this function starts on.
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Get the line number that this function ends on.
     */
    public function getEndLine(): int
    {
        return $this->endLine;
    }

    public function getStartColumn(): int
    {
        return $this->startColumn;
    }

    public function getEndColumn(): int
    {
        return $this->endColumn;
    }

    /**
     * Is this function declared as a reference.
     */
    public function returnsReference(): bool
    {
        return $this->byRef;
    }

    /**
     * Get the return type declaration
     * @return \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    public function getReturnType()
    {
        if ($this->hasTentativeReturnType()) {
            return null;
        }

        return $this->returnType;
    }

    /**
     * Do we have a return type declaration
     */
    public function hasReturnType(): bool
    {
        if ($this->hasTentativeReturnType()) {
            return false;
        }

        return $this->returnType !== null;
    }

    public function hasTentativeReturnType(): bool
    {
        if ($this->isUserDefined()) {
            return false;
        }

        return AnnotationHelper::hasTentativeReturnType($this->getDocComment());
    }

    /**
     * @return \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    public function getTentativeReturnType()
    {
        if (! $this->hasTentativeReturnType()) {
            return null;
        }

        return $this->returnType;
    }

    /**
     * @param \PhpParser\Node\ComplexType|\PhpParser\Node\Identifier|\PhpParser\Node\Name|null $returnType
     * @return \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    private function createReturnType($returnType)
    {
        assert($returnType instanceof Node\Identifier || $returnType instanceof Node\Name || $returnType instanceof Node\NullableType || $returnType instanceof Node\UnionType || $returnType instanceof Node\IntersectionType || $returnType === null);

        if ($returnType === null) {
            return null;
        }

        return ReflectionType::createFromNode($this->reflector, $this, $returnType);
    }

    /**
     * @return list<ReflectionAttribute>
     */
    public function getAttributesByName(string $name): array
    {
        return ReflectionAttributeHelper::filterAttributesByName($this->getAttributes(), $name);
    }

    /**
     * @param class-string $className
     *
     * @return list<ReflectionAttribute>
     */
    public function getAttributesByInstance(string $className): array
    {
        return ReflectionAttributeHelper::filterAttributesByInstance($this->getAttributes(), $className);
    }
}
