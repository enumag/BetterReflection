<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection;

use Closure;
use OutOfBoundsException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod as MethodNode;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use ReflectionException;
use ReflectionMethod as CoreReflectionMethod;
use PHPStan\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use PHPStan\BetterReflection\Reflection\Exception\ClassDoesNotExist;
use PHPStan\BetterReflection\Reflection\Exception\NoObjectProvided;
use PHPStan\BetterReflection\Reflection\Exception\ObjectNotInstanceOfClass;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionMethodStringCast;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Located\LocatedSource;
use PHPStan\BetterReflection\Util\ClassExistenceChecker;

use function assert;
use function sprintf;
use function strtolower;

class ReflectionMethod
{
    use ReflectionFunctionAbstract;

    /** @var list<ReflectionAttribute> */
    private $attributes;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var string
     */
    private $name;
    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Located\LocatedSource
     */
    private $locatedSource;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionClass
     */
    private $declaringClass;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionClass
     */
    private $implementingClass;
    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionClass
     */
    private $currentClass;
    /**
     * @var string|null
     */
    private $aliasName;
    /**
     * @param MethodNode|\PhpParser\Node\Expr\ArrowFunction|\PhpParser\Node\Expr\Closure|\PhpParser\Node\Stmt\Function_ $node
     */
    private function __construct(Reflector $reflector, $node, LocatedSource $locatedSource, ?NamespaceNode $declaringNamespace, ReflectionClass $declaringClass, ReflectionClass $implementingClass, ReflectionClass $currentClass, ?string $aliasName)
    {
        $this->reflector = $reflector;
        $this->locatedSource = $locatedSource;
        $this->declaringClass = $declaringClass;
        $this->implementingClass = $implementingClass;
        $this->currentClass = $currentClass;
        $this->aliasName = $aliasName;
        assert($node instanceof MethodNode);
        $this->name = $node->name->name;
        $this->flags = $node->flags;
        $this->populateTrait($node, $declaringNamespace);
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
    }
    /**
     * @internal
     */
    public static function createFromNode(Reflector $reflector, MethodNode $node, LocatedSource $locatedSource, ?Namespace_ $namespace, ReflectionClass $declaringClass, ReflectionClass $implementingClass, ReflectionClass $currentClass, ?string $aliasName = null): self
    {
        return new self($reflector, $node, $locatedSource, $namespace, $declaringClass, $implementingClass, $currentClass, $aliasName);
    }
    public function forTrait(ReflectionClass $class, int $newFlags, ?string $aliasMethodName): self
    {
        $self = clone $this;
        $self->cachedName = null;
        $self->implementingClass = $class;
        $self->currentClass = $class;
        $self->flags = $newFlags;
        $self->aliasName = $aliasMethodName;
        $parameters = [];
        foreach ($this->parameters as $parameter) {
            $parameters[] = $parameter->changeFunction($self);
        }
        $self->parameters = $parameters;
        return $self;
    }

    public function forClass(ReflectionClass $class): self
    {
        $self = clone $this;
        $self->currentClass = $class;
        $self->returnType = $self->createReturnType($this->astReturnType);

        return $self;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Create a reflection of a method by it's name using a named class
     *
     * @throws IdentifierNotFound
     * @throws OutOfBoundsException
     */
    public static function createFromName(string $className, string $methodName): self
    {
        return ReflectionClass::createFromName($className)->getMethod($methodName);
    }

    /**
     * Create a reflection of a method by it's name using an instance
     *
     * @throws ReflectionException
     * @throws IdentifierNotFound
     * @throws OutOfBoundsException
     */
    public static function createFromInstance(object $instance, string $methodName): self
    {
        return ReflectionClass::createFromInstance($instance)->getMethod($methodName);
    }

    /**
     * @return list<ReflectionAttribute>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getShortName(): string
    {
        return $this->aliasName !== null ? $this->aliasName : $this->name;
    }

    public function getAliasName(): ?string
    {
        return $this->aliasName;
    }

    /**
     * Find the prototype for this method, if it exists. If it does not exist
     * it will throw a MethodPrototypeNotFound exception.
     *
     * @throws Exception\MethodPrototypeNotFound
     */
    public function getPrototype(): self
    {
        $currentClass = $this->getImplementingClass();

        while ($currentClass) {
            foreach ($currentClass->getImmediateInterfaces() as $interface) {
                if ($interface->hasMethod($this->getName())) {
                    return $interface->getMethod($this->getName());
                }
            }

            $currentClass = $currentClass->getParentClass();

            if ($currentClass === null || ! $currentClass->hasMethod($this->getName())) {
                break;
            }

            $prototype = $currentClass->getMethod($this->getName())->findPrototype();

            if ($prototype !== null) {
                if ($this->isConstructor() && ! $prototype->isAbstract()) {
                    break;
                }

                return $prototype;
            }
        }

        throw new Exception\MethodPrototypeNotFound(sprintf('Method %s::%s does not have a prototype', $this->getDeclaringClass()->getName(), $this->getName()));
    }

    private function findPrototype(): ?self
    {
        if ($this->isAbstract()) {
            return $this;
        }

        if ($this->isPrivate()) {
            return null;
        }

        try {
            return $this->getPrototype();
        } catch (Exception\MethodPrototypeNotFound $exception) {
            return $this;
        }
    }

    /**
     * Get the core-reflection-compatible modifier values.
     */
    public function getModifiers(): int
    {
        $val  = $this->isStatic() ? CoreReflectionMethod::IS_STATIC : 0;
        $val += $this->isPublic() ? CoreReflectionMethod::IS_PUBLIC : 0;
        $val += $this->isProtected() ? CoreReflectionMethod::IS_PROTECTED : 0;
        $val += $this->isPrivate() ? CoreReflectionMethod::IS_PRIVATE : 0;
        $val += $this->isAbstract() ? CoreReflectionMethod::IS_ABSTRACT : 0;
        $val += $this->isFinal() ? CoreReflectionMethod::IS_FINAL : 0;

        return $val;
    }

    public function __toString(): string
    {
        return ReflectionMethodStringCast::toString($this);
    }

    public function inNamespace(): bool
    {
        return false;
    }

    public function getNamespaceName(): string
    {
        return '';
    }

    public function isClosure(): bool
    {
        return false;
    }

    /**
     * Is the method abstract.
     */
    public function isAbstract(): bool
    {
        return (bool) ($this->flags & Class_::MODIFIER_ABSTRACT) || $this->declaringClass->isInterface();
    }

    /**
     * Is the method final.
     */
    public function isFinal(): bool
    {
        return (bool) ($this->flags & Class_::MODIFIER_FINAL);
    }

    /**
     * Is the method private visibility.
     */
    public function isPrivate(): bool
    {
        return (bool) ($this->flags & Class_::MODIFIER_PRIVATE);
    }

    /**
     * Is the method protected visibility.
     */
    public function isProtected(): bool
    {
        return (bool) ($this->flags & Class_::MODIFIER_PROTECTED);
    }

    /**
     * Is the method public visibility.
     */
    public function isPublic(): bool
    {
        return ($this->flags & Class_::MODIFIER_PUBLIC) !== 0
            || ($this->flags & Class_::VISIBILITY_MODIFIER_MASK) === 0;
    }

    /**
     * Is the method static.
     */
    public function isStatic(): bool
    {
        return (bool) ($this->flags & Class_::MODIFIER_STATIC);
    }

    /**
     * Is the method a constructor.
     */
    public function isConstructor(): bool
    {
        if (strtolower($this->getName()) === '__construct') {
            return true;
        }

        $declaringClass = $this->getDeclaringClass();
        if ($declaringClass->inNamespace()) {
            return false;
        }

        return strtolower($this->getName()) === strtolower($declaringClass->getShortName());
    }

    /**
     * Is the method a destructor.
     */
    public function isDestructor(): bool
    {
        return strtolower($this->getName()) === '__destruct';
    }

    /**
     * Get the class that declares this method.
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return $this->declaringClass;
    }

    /**
     * Get the class that implemented the method based on trait use.
     */
    public function getImplementingClass(): ReflectionClass
    {
        return $this->implementingClass;
    }

    /**
     * Get the current reflected class.
     */
    public function getCurrentClass(): ReflectionClass
    {
        return $this->currentClass;
    }

    /**
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws ObjectNotInstanceOfClass
     */
    public function getClosure(?object $object = null): Closure
    {
        $declaringClassName = $this->getDeclaringClass()->getName();

        if ($this->isStatic()) {
            $this->assertClassExist($declaringClassName);

            return function (...$args) {
                return $this->callStaticMethod($args);
            };
        }

        $instance = $this->assertObject($object);

        return function (...$args) use ($instance) {
            return $this->callObjectMethod($instance, $args);
        };
    }

    /**
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws ObjectNotInstanceOfClass
     * @param mixed ...$args
     * @return mixed
     */
    public function invoke(?object $object = null, ...$args)
    {
        return $this->invokeArgs($object, $args);
    }

    /**
     * @param array<mixed> $args
     *
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws ObjectNotInstanceOfClass
     * @return mixed
     */
    public function invokeArgs(?object $object = null, array $args = [])
    {
        $implementingClassName = $this->getImplementingClass()->getName();

        if ($this->isStatic()) {
            $this->assertClassExist($implementingClassName);

            return $this->callStaticMethod($args);
        }

        return $this->callObjectMethod($this->assertObject($object), $args);
    }

    /**
     * @param array<mixed> $args
     * @return mixed
     */
    private function callStaticMethod(array $args)
    {
        $implementingClassName = $this->getImplementingClass()->getName();

        /** @psalm-suppress InvalidStringClass */
        $closure = Closure::bind(function (string $implementingClassName, string $_methodName, array $methodArgs) {
            return $implementingClassName::{$_methodName}(...$methodArgs);
        }, null, $implementingClassName);

        assert($closure instanceof Closure);

        return $closure->__invoke($implementingClassName, $this->getName(), $args);
    }

    /**
     * @param array<mixed> $args
     * @return mixed
     */
    private function callObjectMethod(object $object, array $args)
    {
        /** @psalm-suppress MixedMethodCall */
        $closure = Closure::bind(function (object $object, string $methodName, array $methodArgs) {
            return $object->{$methodName}(...$methodArgs);
        }, $object, $this->getImplementingClass()->getName());

        assert($closure instanceof Closure);

        return $closure->__invoke($object, $this->getName(), $args);
    }

    /**
     * @throws ClassDoesNotExist
     */
    private function assertClassExist(string $className): void
    {
        if (! ClassExistenceChecker::classExists($className) && ! ClassExistenceChecker::traitExists($className)) {
            throw new ClassDoesNotExist(sprintf('Method of class %s cannot be used as the class is not loaded', $className));
        }
    }

    /**
     * @throws NoObjectProvided
     * @throws ObjectNotInstanceOfClass
     */
    private function assertObject(?object $object): object
    {
        if ($object === null) {
            throw NoObjectProvided::create();
        }

        $implementingClassName = $this->getImplementingClass()->getName();

        if (get_class($object) !== $implementingClassName) {
            throw ObjectNotInstanceOfClass::fromClassName($implementingClassName);
        }

        return $object;
    }
}
