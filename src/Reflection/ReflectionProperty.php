<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection;

use Closure;
use Error;
use LogicException;
use OutOfBoundsException;
use PhpParser\Node;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Property as PropertyNode;
use ReflectionException;
use ReflectionProperty as CoreReflectionProperty;
use PHPStan\BetterReflection\NodeCompiler\CompiledValue;
use PHPStan\BetterReflection\NodeCompiler\CompileNodeToValue;
use PHPStan\BetterReflection\NodeCompiler\CompilerContext;
use PHPStan\BetterReflection\Reflection\Annotation\AnnotationHelper;
use PHPStan\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use PHPStan\BetterReflection\Reflection\Exception\ClassDoesNotExist;
use PHPStan\BetterReflection\Reflection\Exception\NoObjectProvided;
use PHPStan\BetterReflection\Reflection\Exception\NotAnObject;
use PHPStan\BetterReflection\Reflection\Exception\ObjectNotInstanceOfClass;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionPropertyStringCast;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\Util\CalculateReflectionColumn;
use PHPStan\BetterReflection\Util\ClassExistenceChecker;
use PHPStan\BetterReflection\Util\Exception\NoNodePosition;
use PHPStan\BetterReflection\Util\GetLastDocComment;

use function assert;
use function func_num_args;
use function is_object;
use function sprintf;
use function str_contains;

class ReflectionProperty
{
    /**
     * @var \PHPStan\BetterReflection\NodeCompiler\CompiledValue|null
     */
    private $compiledDefaultValue;

    /** @var list<ReflectionAttribute> */
    private $attributes;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isPrivate;

    /**
     * @var bool
     */
    private $isProtected;

    /**
     * @var bool
     */
    private $isPublic;

    /**
     * @var bool
     */
    private $isStatic;

    /**
     * @var bool
     */
    private $isReadOnly;

    /**
     * @var string
     */
    private $docComment;

    /**
     * @var \PhpParser\Node\Expr|null
     */
    private $defaultExpr;

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
    private $allowsNull;

    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType|\PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType|null
     */
    private $type;
    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var int
     */
    private $positionInNode;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionClass
     */
    private $declaringClass;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionClass
     */
    private $implementingClass;
    /**
     * @var bool
     */
    private $isPromoted;
    /**
     * @var bool
     */
    private $declaredAtCompileTime;
    private function __construct(Reflector $reflector, PropertyNode $node, int $positionInNode, ReflectionClass $declaringClass, ReflectionClass $implementingClass, bool $isPromoted, bool $declaredAtCompileTime)
    {
        $this->reflector = $reflector;
        $this->positionInNode = $positionInNode;
        $this->declaringClass = $declaringClass;
        $this->implementingClass = $implementingClass;
        $this->isPromoted = $isPromoted;
        $this->declaredAtCompileTime = $declaredAtCompileTime;
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
        $this->name = $node->props[$this->positionInNode]->name->name;
        $this->isPrivate = $node->isPrivate();
        $this->isProtected = $node->isProtected();
        $this->isPublic = $node->isPublic();
        $this->isStatic = $node->isStatic();
        $this->isReadOnly = $node->isReadonly();
        $this->docComment = GetLastDocComment::forNode($node);
        $this->defaultExpr = $node->props[$this->positionInNode]->default;
        $this->startLine = $node->getStartLine();
        $this->endLine = $node->getEndLine();
        try {
            $this->startColumn = CalculateReflectionColumn::getStartColumn($this->declaringClass->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->startColumn = -1;
        }
        try {
            $this->endColumn = CalculateReflectionColumn::getEndColumn($this->declaringClass->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->endColumn = -1;
        }
        $this->allowsNull = $node->type instanceof NullableType;
        $this->type = $this->createType($node);
    }

    public function forClass(ReflectionClass $class): self
    {
        $self = clone $this;
        $self->implementingClass = $class;

        return $self;
    }

    /**
     * Create a reflection of a class's property by its name
     *
     * @throws OutOfBoundsException
     */
    public static function createFromName(string $className, string $propertyName): self
    {
        $property = ReflectionClass::createFromName($className)->getProperty($propertyName);

        if ($property === null) {
            throw new OutOfBoundsException(sprintf('Could not find property: %s', $propertyName));
        }

        return $property;
    }

    /**
     * Create a reflection of an instance's property by its name
     *
     * @throws ReflectionException
     * @throws IdentifierNotFound
     * @throws OutOfBoundsException
     */
    public static function createFromInstance(object $instance, string $propertyName): self
    {
        $property = ReflectionClass::createFromInstance($instance)->getProperty($propertyName);

        if ($property === null) {
            throw new OutOfBoundsException(sprintf('Could not find property: %s', $propertyName));
        }

        return $property;
    }

    public function __toString(): string
    {
        return ReflectionPropertyStringCast::toString($this);
    }

    /**
     * @internal
     *
     * @param PropertyNode $node Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     */
    public static function createFromNode(Reflector $reflector, PropertyNode $node, int $positionInNode, ReflectionClass $declaringClass, ReflectionClass $implementingClass, bool $isPromoted, bool $declaredAtCompileTime = true): self
    {
        return new self($reflector, $node, $positionInNode, $declaringClass, $implementingClass, $isPromoted, $declaredAtCompileTime);
    }

    /**
     * Has the property been declared at compile-time?
     *
     * Note that unless the property is static, this is hard coded to return
     * true, because we are unable to reflect instances of classes, therefore
     * we can be sure that all properties are always declared at compile-time.
     */
    public function isDefault(): bool
    {
        return $this->declaredAtCompileTime;
    }

    /**
     * Get the core-reflection-compatible modifier values.
     */
    public function getModifiers(): int
    {
        $val  = $this->isStatic() ? CoreReflectionProperty::IS_STATIC : 0;
        $val += $this->isPublic() ? CoreReflectionProperty::IS_PUBLIC : 0;
        $val += $this->isProtected() ? CoreReflectionProperty::IS_PROTECTED : 0;
        $val += $this->isPrivate() ? CoreReflectionProperty::IS_PRIVATE : 0;

        return $val;
    }

    /**
     * Get the name of the property.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Is the property private?
     */
    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    /**
     * Is the property protected?
     */
    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    /**
     * Is the property public?
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * Is the property static?
     */
    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    public function isPromoted(): bool
    {
        return $this->isPromoted;
    }

    public function isInitialized(?object $object = null): bool
    {
        if ($object === null && $this->isStatic()) {
            return ! $this->hasType() || $this->hasDefaultValue();
        }

        try {
            $this->getValue($object);

            return true;

        // @phpstan-ignore-next-line
        } catch (Error $e) {
            if (strpos($e->getMessage(), 'must not be accessed before initialization') !== false) {
                return false;
            }

            throw $e;
        }
    }

    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }

    public function getDeclaringClass(): ReflectionClass
    {
        return $this->declaringClass;
    }

    public function getImplementingClass(): ReflectionClass
    {
        return $this->implementingClass;
    }

    public function getDocComment(): string
    {
        return $this->docComment;
    }

    public function hasDefaultValue(): bool
    {
        return ! $this->hasType() || $this->defaultExpr !== null;
    }

    /**
     * Get the default value of the property (as defined before constructor is
     * called, when the property is defined)
     *
     * @deprecated Use getDefaultValueExpr()
     * @return mixed
     */
    public function getDefaultValue()
    {
        $defaultValueNode = $this->defaultExpr;

        if ($defaultValueNode === null) {
            return null;
        }

        if ($this->compiledDefaultValue === null) {
            $this->compiledDefaultValue = (new CompileNodeToValue())->__invoke($defaultValueNode, new CompilerContext($this->reflector, $this));
        }

        /** @psalm-var scalar|array<scalar>|null $value */
        $value = $this->compiledDefaultValue->value;

        return $value;
    }

    public function getDefaultValueExpr(): Node\Expr
    {
        if ($this->defaultExpr === null) {
            throw new LogicException('This property does not have a default value available');
        }

        return $this->defaultExpr;
    }

    public function isDeprecated(): bool
    {
        return AnnotationHelper::isDeprecated($this->getDocComment());
    }

    /**
     * Get the line number that this property starts on.
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Get the line number that this property ends on.
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

    public function getPositionInAst(): int
    {
        return $this->positionInNode;
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

    /**
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws ObjectNotInstanceOfClass
     * @return mixed
     */
    public function getValue(?object $object = null)
    {
        $implementingClassName = $this->getImplementingClass()->getName();

        if ($this->isStatic()) {
            $this->assertClassExist($implementingClassName);

            $closure = Closure::bind(function (string $implementingClassName, string $propertyName) {
                return $implementingClassName::${$propertyName};
            }, null, $implementingClassName);

            assert($closure instanceof Closure);

            return $closure->__invoke($implementingClassName, $this->getName());
        }

        $instance = $this->assertObject($object);

        $closure = Closure::bind(function (object $instance, string $propertyName) {
            return $instance->{$propertyName};
        }, $instance, $implementingClassName);

        assert($closure instanceof Closure);

        return $closure->__invoke($instance, $this->getName());
    }

    /**
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws NotAnObject
     * @throws ObjectNotInstanceOfClass
     * @param mixed $object
     * @param mixed $value
     */
    public function setValue($object, $value = null): void
    {
        $implementingClassName = $this->getImplementingClass()->getName();

        if ($this->isStatic()) {
            $this->assertClassExist($implementingClassName);

            $closure = Closure::bind(function (string $_implementingClassName, string $_propertyName, $value): void {
                /** @psalm-suppress MixedAssignment */
                $_implementingClassName::${$_propertyName} = $value;
            }, null, $implementingClassName);

            assert($closure instanceof Closure);

            $closure->__invoke($implementingClassName, $this->getName(), func_num_args() === 2 ? $value : $object);

            return;
        }

        $instance = $this->assertObject($object);

        $closure = Closure::bind(function (object $instance, string $propertyName, $value): void {
            $instance->{$propertyName} = $value;
        }, $instance, $implementingClassName);

        assert($closure instanceof Closure);

        $closure->__invoke($instance, $this->getName(), $value);
    }

    /**
     * Does this property allow null?
     */
    public function allowsNull(): bool
    {
        if (! $this->hasType()) {
            return true;
        }

        return $this->allowsNull;
    }

    /**
     * Get the ReflectionType instance representing the type declaration for
     * this property
     *
     * (note: this has nothing to do with DocBlocks).
     * @return \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType|\PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return \PHPStan\BetterReflection\Reflection\ReflectionIntersectionType|\PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType|null
     */
    private function createType(PropertyNode $node)
    {
        $type = $node->type;
        assert($type instanceof Node\Identifier || $type instanceof Node\Name || $type instanceof Node\NullableType || $type instanceof Node\UnionType || $type instanceof Node\IntersectionType || $type === null);

        if ($type === null) {
            return null;
        }

        return ReflectionType::createFromNode($this->reflector, $this, $type);
    }

    /**
     * Does this property have a type declaration?
     *
     * (note: this has nothing to do with DocBlocks).
     */
    public function hasType(): bool
    {
        return $this->type !== null;
    }

    /**
     * @param class-string $className
     *
     * @throws ClassDoesNotExist
     */
    private function assertClassExist(string $className): void
    {
        if (! ClassExistenceChecker::classExists($className) && ! ClassExistenceChecker::traitExists($className)) {
            throw new ClassDoesNotExist('Property cannot be retrieved as the class is not loaded');
        }
    }

    /**
     * @throws NoObjectProvided
     * @throws NotAnObject
     * @throws ObjectNotInstanceOfClass
     *
     * @psalm-assert object $object
     * @param mixed $object
     */
    private function assertObject($object): object
    {
        if ($object === null) {
            throw NoObjectProvided::create();
        }

        if (! is_object($object)) {
            throw NotAnObject::fromNonObject($object);
        }

        $implementingClassName = $this->getImplementingClass()->getName();

        if (get_class($object) !== $implementingClassName) {
            throw ObjectNotInstanceOfClass::fromClassName($implementingClassName);
        }

        return $object;
    }
}
