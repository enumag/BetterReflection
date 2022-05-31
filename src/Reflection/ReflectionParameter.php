<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use Closure;
use Exception;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use PhpParser\Node;
use PhpParser\Node\Param as ParamNode;
use Roave\BetterReflection\NodeCompiler\CompiledValue;
use Roave\BetterReflection\NodeCompiler\CompileNodeToValue;
use Roave\BetterReflection\NodeCompiler\CompilerContext;
use Roave\BetterReflection\NodeCompiler\Exception\UnableToCompileNode;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\Reflection\StringCast\ReflectionParameterStringCast;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\Util\CalculateReflectionColumn;

use Roave\BetterReflection\Util\Exception\NoNodePosition;
use function assert;
use function count;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function strtolower;

class ReflectionParameter
{
    /**
     * @var bool
     */
    private $isOptional;

    /**
     * @var \Roave\BetterReflection\NodeCompiler\CompiledValue|null
     */
    private $compiledDefaultValue;

    /** @var list<ReflectionAttribute> */
    private $attributes;

    /**
     * @var \PhpParser\Node\Expr|null
     */
    private $defaultExpr;

    /**
     * @var \PhpParser\Node\ComplexType|\PhpParser\Node\Identifier|\PhpParser\Node\Name|null
     */
    private $astType;

    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $variadic;

    /**
     * @var bool
     */
    private $byRef;

    /**
     * @var bool
     */
    private $isPromoted;

    /**
     * @var int
     */
    private $startColumn;

    /**
     * @var int
     */
    private $endColumn;
    /**
     * @var \Roave\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod
     */
    private $function;
    /**
     * @var int
     */
    private $parameterIndex;
    /**
     * @var bool
     */
    private $optional;
    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod $function
     */
    private function __construct(Reflector $reflector, ParamNode $node, $function, int $parameterIndex, bool $optional)
    {
        $this->reflector = $reflector;
        $this->function = $function;
        $this->parameterIndex = $parameterIndex;
        $this->optional = $optional;
        $this->isOptional = $this->optional;
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
        $this->defaultExpr = $node->default;
        $this->astType = $node->type;
        $this->type = $this->createType($node->type);
        assert($node->var instanceof Node\Expr\Variable);
        assert(is_string($node->var->name));
        $this->name = $node->var->name;
        $this->variadic = $node->variadic;
        $this->byRef = $node->byRef;
        $this->isPromoted = $node->flags !== 0;
        try {
            $this->startColumn = CalculateReflectionColumn::getStartColumn($this->function->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->startColumn = -1;
        }
        try {
            $this->endColumn = CalculateReflectionColumn::getEndColumn($this->function->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->endColumn = -1;
        }
    }

    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod $function
     */
    public function changeFunction($function): self
    {
        $self = clone $this;
        $self->function = $function;
        $self->type = $self->createType($self->astType);

        return $self;
    }

    /**
     * Create a reflection of a parameter using a class name
     *
     * @throws OutOfBoundsException
     */
    public static function createFromClassNameAndMethod(string $className, string $methodName, string $parameterName): self
    {
        $parameter = ReflectionClass::createFromName($className)
            ->getMethod($methodName)
            ->getParameter($parameterName);
        if ($parameter === null) {
            throw new OutOfBoundsException(sprintf('Could not find parameter: %s', $parameterName));
        }
        return $parameter;
    }

    /**
     * Create a reflection of a parameter using an instance
     *
     * @throws OutOfBoundsException
     */
    public static function createFromClassInstanceAndMethod(object $instance, string $methodName, string $parameterName): self
    {
        $parameter = ReflectionClass::createFromInstance($instance)
            ->getMethod($methodName)
            ->getParameter($parameterName);
        if ($parameter === null) {
            throw new OutOfBoundsException(sprintf('Could not find parameter: %s', $parameterName));
        }
        return $parameter;
    }

    /**
     * Create a reflection of a parameter using a closure
     *
     * @throws OutOfBoundsException
     */
    public static function createFromClosure(Closure $closure, string $parameterName): ReflectionParameter
    {
        $parameter = ReflectionFunction::createFromClosure($closure)
            ->getParameter($parameterName);

        if ($parameter === null) {
            throw new OutOfBoundsException(sprintf('Could not find parameter: %s', $parameterName));
        }

        return $parameter;
    }

    /**
     * Create the parameter from the given spec. Possible $spec parameters are:
     *
     *  - [$instance, 'method']
     *  - ['Foo', 'bar']
     *  - ['foo']
     *  - [function () {}]
     *
     * @param object[]|string[]|string|Closure $spec
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function createFromSpec($spec, string $parameterName): self
    {
        try {
            if (is_array($spec) && count($spec) === 2 && is_string($spec[1])) {
                if (is_object($spec[0])) {
                    return self::createFromClassInstanceAndMethod($spec[0], $spec[1], $parameterName);
                }

                return self::createFromClassNameAndMethod($spec[0], $spec[1], $parameterName);
            }

            if (is_string($spec)) {
                $parameter = ReflectionFunction::createFromName($spec)->getParameter($parameterName);
                if ($parameter === null) {
                    throw new OutOfBoundsException(sprintf('Could not find parameter: %s', $parameterName));
                }

                return $parameter;
            }

            if ($spec instanceof Closure) {
                return self::createFromClosure($spec, $parameterName);
            }
        } catch (OutOfBoundsException $e) {
            throw new InvalidArgumentException('Could not create reflection from the spec given', 0, $e);
        }

        throw new InvalidArgumentException('Could not create reflection from the spec given');
    }

    public function __toString(): string
    {
        return ReflectionParameterStringCast::toString($this);
    }

    /**
     * @internal
     *
     * @param ParamNode $node Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     * @param \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod $function
     */
    public static function createFromNode(Reflector $reflector, ParamNode $node, $function, int $parameterIndex, bool $optional): self
    {
        return new self($reflector, $node, $function, $parameterIndex, $optional);
    }

    /**
     * @throws LogicException
     */
    private function getCompiledDefaultValue(): CompiledValue
    {
       $defaultExpr = $this->defaultExpr;
       if ($defaultExpr === null) {
           throw new LogicException('This parameter does not have a default value available');
       }

        if ($this->compiledDefaultValue === null) {
            $this->compiledDefaultValue = (new CompileNodeToValue())->__invoke($defaultExpr, new CompilerContext($this->reflector, $this));
        }

        return $this->compiledDefaultValue;
    }

    /**
     * Get the name of the parameter.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the function (or method) that declared this parameter.
     * @return \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod
     */
    public function getDeclaringFunction()
    {
        return $this->function;
    }

    /**
     * Get the class from the method that this parameter belongs to, if it
     * exists.
     *
     * This will return null if the declaring function is not a method.
     */
    public function getDeclaringClass(): ?ReflectionClass
    {
        if ($this->function instanceof ReflectionMethod) {
            return $this->function->getDeclaringClass();
        }

        return null;
    }

    public function getImplementingClass(): ?ReflectionClass
    {
        if ($this->function instanceof ReflectionMethod) {
            return $this->function->getImplementingClass();
        }

        return null;
    }

    /**
     * Is the parameter optional?
     *
     * Note this is distinct from "isDefaultValueAvailable" because you can have
     * a default value, but the parameter not be optional. In the example, the
     * $foo parameter isOptional() == false, but isDefaultValueAvailable == true
     *
     * @example someMethod($foo = 'foo', $bar)
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * Does the parameter have a default, regardless of whether it is optional.
     *
     * Note this is distinct from "isOptional" because you can have
     * a default value, but the parameter not be optional. In the example, the
     * $foo parameter isOptional() == false, but isDefaultValueAvailable == true
     *
     * @example someMethod($foo = 'foo', $bar)
     * @psalm-assert-if-true Node\Expr $this->node->default
     */
    public function isDefaultValueAvailable(): bool
    {
        return $this->defaultExpr !== null;
    }

    /**
     * Get the default value of the parameter.
     *
     * @deprecated Use getDefaultValueExpr()
     * @throws LogicException
     * @throws UnableToCompileNode
     * @return mixed
     */
    public function getDefaultValue()
    {
        /** @psalm-var scalar|array<scalar>|null $value */
        $value = $this->getCompiledDefaultValue()->value;

        return $value;
    }

    public function getDefaultValueExpr(): Node\Expr
    {
        if ($this->defaultExpr === null) {
            throw new LogicException('This parameter does not have a default value available');
        }

        return $this->defaultExpr;
    }

    /**
     * Does this method allow null for a parameter?
     */
    public function allowsNull(): bool
    {
        $type = $this->getType();

        if ($type === null) {
            return true;
        }

        return $type->allowsNull();
    }

    /**
     * Find the position of the parameter, left to right, starting at zero.
     */
    public function getPosition(): int
    {
        return $this->parameterIndex;
    }

    /**
     * Get the ReflectionType instance representing the type declaration for
     * this parameter
     *
     * (note: this has nothing to do with DocBlocks).
     * @return \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param \PhpParser\Node\ComplexType|\PhpParser\Node\Identifier|\PhpParser\Node\Name|null $type
     * @return \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null
     */
    public function createType($type)
    {
        assert($type instanceof Node\Identifier || $type instanceof Node\Name || $type instanceof Node\NullableType || $type instanceof Node\UnionType || $type instanceof Node\IntersectionType || $type === null);

        if ($type === null) {
            return null;
        }

        $allowsNull = $this->defaultExpr instanceof Node\Expr\ConstFetch && $this->defaultExpr->name->toLowerString() === 'null';

        return ReflectionType::createFromNode($this->reflector, $this, $type, $allowsNull);
    }

    /**
     * Does this parameter have a type declaration?
     *
     * (note: this has nothing to do with DocBlocks).
     */
    public function hasType(): bool
    {
        return $this->astType !== null;
    }

    /**
     * Is this parameter an array?
     */
    public function isArray(): bool
    {
        return $this->isType($this->getType(), 'array');
    }

    /**
     * Is this parameter a callable?
     */
    public function isCallable(): bool
    {
        return $this->isType($this->getType(), 'callable');
    }

    /**
     * For isArray() and isCallable().
     * @param \Roave\BetterReflection\Reflection\ReflectionIntersectionType|\Roave\BetterReflection\Reflection\ReflectionNamedType|\Roave\BetterReflection\Reflection\ReflectionUnionType|null $typeReflection
     */
    private function isType($typeReflection, string $type): bool
    {
        if ($typeReflection === null) {
            return false;
        }

        if ($typeReflection instanceof ReflectionIntersectionType) {
            return false;
        }

        $isOneOfAllowedTypes = static function (ReflectionNamedType $namedType, string ...$types): bool {
            foreach ($types as $type) {
                if (strtolower($namedType->getName()) === $type) {
                    return true;
                }
            }

            return false;
        };

        if ($typeReflection instanceof ReflectionUnionType) {
            $unionTypes = $typeReflection->getTypes();

            foreach ($unionTypes as $unionType) {
                if (! $isOneOfAllowedTypes($unionType, $type, 'null')) {
                    return false;
                }
            }

            return true;
        }

        return $isOneOfAllowedTypes($typeReflection, $type);
    }

    /**
     * Is this parameter a variadic (denoted by ...$param).
     */
    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    /**
     * Is this parameter passed by reference (denoted by &$param).
     */
    public function isPassedByReference(): bool
    {
        return $this->byRef;
    }

    public function canBePassedByValue(): bool
    {
        return ! $this->isPassedByReference();
    }

    public function isPromoted(): bool
    {
        return $this->isPromoted;
    }

    /**
     * @throws LogicException
     */
    public function isDefaultValueConstant(): bool
    {
        return $this->getCompiledDefaultValue()->constantName !== null;
    }

    /**
     * @throws LogicException
     */
    public function getDefaultValueConstantName(): string
    {
        $compiledDefaultValue = $this->getCompiledDefaultValue();

        if ($compiledDefaultValue->constantName === null) {
            throw new LogicException('This parameter is not a constant default value, so cannot have a constant name');
        }

        return $compiledDefaultValue->constantName;
    }

    /**
     * Gets a ReflectionClass for the type hint (returns null if not a class)
     */
    public function getClass(): ?ReflectionClass
    {
        $type = $this->getType();

        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionIntersectionType) {
            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                $innerTypeClass = $this->getClassFromNamedType($innerType);
                if ($innerTypeClass !== null) {
                    return $innerTypeClass;
                }
            }

            return null;
        }

        return $this->getClassFromNamedType($type);
    }

    private function getClassFromNamedType(ReflectionNamedType $namedType): ?ReflectionClass
    {
        try {
            return $namedType->getClass();
        } catch (LogicException $exception) {
            return null;
        }
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
     * @return list<ReflectionAttribute>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
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
