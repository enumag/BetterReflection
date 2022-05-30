<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection\Adapter;

use ReflectionClass as CoreReflectionClass;
use ReflectionException as CoreReflectionException;
use ReflectionExtension as CoreReflectionExtension;
use ReflectionObject as CoreReflectionObject;
use ReturnTypeWillChange;
use Roave\BetterReflection\Reflection\ReflectionAttribute as BetterReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass as BetterReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionClassConstant as BetterReflectionClassConstant;
use Roave\BetterReflection\Reflection\ReflectionMethod as BetterReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionObject as BetterReflectionObject;
use Roave\BetterReflection\Reflection\ReflectionProperty as BetterReflectionProperty;
use Roave\BetterReflection\Util\FileHelper;
use ValueError;

use function array_combine;
use function array_filter;
use function array_map;
use function array_values;
use function func_num_args;
use function sprintf;
use function strtolower;

final class ReflectionObject extends CoreReflectionObject
{
    /**
     * @var BetterReflectionObject
     */
    private $betterReflectionObject;
    public function __construct(BetterReflectionObject $betterReflectionObject)
    {
        $this->betterReflectionObject = $betterReflectionObject;
    }

    public function __toString(): string
    {
        return $this->betterReflectionObject->__toString();
    }

    public function getName(): string
    {
        return $this->betterReflectionObject->getName();
    }

    public function isInternal(): bool
    {
        return $this->betterReflectionObject->isInternal();
    }

    public function isUserDefined(): bool
    {
        return $this->betterReflectionObject->isUserDefined();
    }

    public function isInstantiable(): bool
    {
        return $this->betterReflectionObject->isInstantiable();
    }

    public function isCloneable(): bool
    {
        return $this->betterReflectionObject->isCloneable();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getFileName()
    {
        $fileName = $this->betterReflectionObject->getFileName();

        return $fileName !== null ? FileHelper::normalizeSystemPath($fileName) : false;
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getStartLine()
    {
        return $this->betterReflectionObject->getStartLine();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getEndLine()
    {
        return $this->betterReflectionObject->getEndLine();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getDocComment()
    {
        return $this->betterReflectionObject->getDocComment() ?: false;
    }

    public function getConstructor(): ReflectionMethod
    {
        return new ReflectionMethod($this->betterReflectionObject->getConstructor());
    }

    /**
     * {@inheritDoc}
     */
    public function hasMethod($name): bool
    {
        return $this->betterReflectionObject->hasMethod($this->getMethodRealName($name));
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod($name): ReflectionMethod
    {
        return new ReflectionMethod($this->betterReflectionObject->getMethod($this->getMethodRealName($name)));
    }

    private function getMethodRealName(string $name): string
    {
        $realMethodNames = array_map(static function (BetterReflectionMethod $method) : string {
            return $method->getName();
        }, $this->betterReflectionObject->getMethods());

        $methodNames = array_combine(array_map(static function (string $methodName) : string {
            return strtolower($methodName);
        }, $realMethodNames), $realMethodNames);

        return $methodNames[strtolower($name)] ?? $name;
    }

    /**
     * @param int|null $filter
     * @return ReflectionMethod[]
     */
    public function getMethods($filter = null): array
    {
        return array_map(static function (BetterReflectionMethod $method) : ReflectionMethod {
            return new ReflectionMethod($method);
        }, $this->betterReflectionObject->getMethods($filter));
    }

    /**
     * {@inheritDoc}
     */
    public function hasProperty($name): bool
    {
        return $this->betterReflectionObject->hasProperty($name);
    }

    /**
     * @param string $name
     * @return ReflectionProperty
     */
    public function getProperty($name): \ReflectionProperty
    {
        $property = $this->betterReflectionObject->getProperty($name);

        if ($property === null) {
            throw new CoreReflectionException(sprintf('Property "%s" does not exist', $name));
        }

        return new ReflectionProperty($property);
    }

    /**
     * @param int|null $filter
     * @return ReflectionProperty[]
     */
    public function getProperties($filter = null): array
    {
        return array_values(array_map(static function (BetterReflectionProperty $property) : ReflectionProperty {
            return new ReflectionProperty($property);
        }, $this->betterReflectionObject->getProperties()));
    }

    /**
     * {@inheritDoc}
     */
    public function hasConstant($name): bool
    {
        return $this->betterReflectionObject->hasConstant($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConstants(?int $filter = null): array
    {
        $reflectionConstants = $this->betterReflectionObject->getReflectionConstants();

        if ($filter !== null) {
            $reflectionConstants = array_filter($reflectionConstants, static function (BetterReflectionClassConstant $betterConstant) use ($filter) : bool {
                return (bool) ($betterConstant->getModifiers() & $filter);
            });
        }

        return array_map(static function (BetterReflectionClassConstant $betterConstant) {
            return $betterConstant->getValue();
        }, $reflectionConstants);
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getConstant($name)
    {
        return $this->betterReflectionObject->getConstant($name);
    }

    /**
     * @param string $name
     * @return ReflectionClassConstant|false
     */
    #[ReturnTypeWillChange]
    public function getReflectionConstant($name)
    {
        $betterReflectionConstant = $this->betterReflectionObject->getReflectionConstant($name);

        if ($betterReflectionConstant === null) {
            return false;
        }

        return new ReflectionClassConstant($betterReflectionConstant);
    }

    /**
     * @return list<ReflectionClassConstant>
     */
    public function getReflectionConstants(?int $filter = null): array
    {
        return array_values(array_map(static function (BetterReflectionClassConstant $betterConstant) : ReflectionClassConstant {
            return new ReflectionClassConstant($betterConstant);
        }, $this->betterReflectionObject->getReflectionConstants()));
    }

    /**
     * @return array<class-string, ReflectionClass>
     */
    public function getInterfaces(): array
    {
        return array_map(static function (BetterReflectionClass $interface) : ReflectionClass {
            return new ReflectionClass($interface);
        }, $this->betterReflectionObject->getInterfaces());
    }

    /**
     * @return list<class-string>
     */
    public function getInterfaceNames(): array
    {
        return $this->betterReflectionObject->getInterfaceNames();
    }

    public function isInterface(): bool
    {
        return $this->betterReflectionObject->isInterface();
    }

    /**
     * @return array<trait-string, ReflectionClass>
     */
    public function getTraits(): array
    {
        $traits = $this->betterReflectionObject->getTraits();

        /** @var list<trait-string> $traitNames */
        $traitNames = array_map(static function (BetterReflectionClass $trait) : string {
            return $trait->getName();
        }, $traits);

        return array_combine($traitNames, array_map(static function (BetterReflectionClass $trait) : ReflectionClass {
            return new ReflectionClass($trait);
        }, $traits));
    }

    /**
     * @return list<trait-string>
     */
    public function getTraitNames(): array
    {
        return $this->betterReflectionObject->getTraitNames();
    }

    /**
     * @return array<string, string>
     */
    public function getTraitAliases(): array
    {
        return $this->betterReflectionObject->getTraitAliases();
    }

    public function isTrait(): bool
    {
        return $this->betterReflectionObject->isTrait();
    }

    public function isAbstract(): bool
    {
        return $this->betterReflectionObject->isAbstract();
    }

    public function isFinal(): bool
    {
        return $this->betterReflectionObject->isFinal();
    }

    public function getModifiers(): int
    {
        return $this->betterReflectionObject->getModifiers();
    }

    /**
     * {@inheritDoc}
     */
    public function isInstance($object): bool
    {
        return $this->betterReflectionObject->isInstance($object);
    }

    /**
     * @param mixed $arg
     * @param mixed ...$args
     *
     * @return object
     */
    #[ReturnTypeWillChange]
    public function newInstance($arg = null, ...$args)
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function newInstanceWithoutConstructor()
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function newInstanceArgs(?array $args = null)
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * @return ReflectionClass|false
     */
    #[ReturnTypeWillChange]
    public function getParentClass()
    {
        $parentClass = $this->betterReflectionObject->getParentClass();

        if ($parentClass === null) {
            return false;
        }

        return new ReflectionClass($parentClass);
    }

    /**
     * {@inheritDoc}
     */
    public function isSubclassOf($class): bool
    {
        $realParentClassNames = $this->betterReflectionObject->getParentClassNames();

        $parentClassNames = array_combine(array_map(static function (string $parentClassName) : string {
            return strtolower($parentClassName);
        }, $realParentClassNames), $realParentClassNames);

        $className           = $class instanceof CoreReflectionClass ? $class->getName() : $class;
        $lowercasedClassName = strtolower($className);

        $realParentClassName = $parentClassNames[$lowercasedClassName] ?? $className;

        return $this->betterReflectionObject->isSubclassOf($realParentClassName);
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     */
    public function getStaticProperties(): array
    {
        return $this->betterReflectionObject->getStaticProperties();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getStaticPropertyValue($name, $default = null)
    {
        $betterReflectionProperty = $this->betterReflectionObject->getProperty($name);

        if ($betterReflectionProperty === null) {
            if (func_num_args() === 2) {
                return $default;
            }

            throw new CoreReflectionException(sprintf('Property "%s" does not exist', $name));
        }

        $property = new ReflectionProperty($betterReflectionProperty);

        if (! $property->isAccessible()) {
            throw new CoreReflectionException(sprintf('Property "%s" is not accessible', $name));
        }

        if (! $property->isStatic()) {
            throw new CoreReflectionException(sprintf('Property "%s" is not static', $name));
        }

        return $property->getValue();
    }

    /**
     * {@inheritDoc}
     */
    public function setStaticPropertyValue($name, $value): void
    {
        $betterReflectionProperty = $this->betterReflectionObject->getProperty($name);

        if ($betterReflectionProperty === null) {
            throw new CoreReflectionException(sprintf('Property "%s" does not exist', $name));
        }

        $property = new ReflectionProperty($betterReflectionProperty);

        if (! $property->isAccessible()) {
            throw new CoreReflectionException(sprintf('Property "%s" is not accessible', $name));
        }

        if (! $property->isStatic()) {
            throw new CoreReflectionException(sprintf('Property "%s" is not static', $name));
        }

        $property->setValue($value);
    }

    /**
     * @return array<string, scalar|array<scalar>|null>
     */
    public function getDefaultProperties(): array
    {
        return $this->betterReflectionObject->getDefaultProperties();
    }

    public function isIterateable(): bool
    {
        return $this->betterReflectionObject->isIterateable();
    }

    public function isIterable(): bool
    {
        return $this->isIterateable();
    }

    /**
     * @param \ReflectionClass|string $interface
     */
    public function implementsInterface($interface): bool
    {
        $realInterfaceNames = $this->betterReflectionObject->getInterfaceNames();

        $interfaceNames = array_combine(array_map(static function (string $interfaceName) : string {
            return strtolower($interfaceName);
        }, $realInterfaceNames), $realInterfaceNames);

        $interfaceName          = $interface instanceof CoreReflectionClass ? $interface->getName() : $interface;
        $lowercasedIntefaceName = strtolower($interfaceName);

        $realInterfaceName = $interfaceNames[$lowercasedIntefaceName] ?? $interfaceName;

        return $this->betterReflectionObject->implementsInterface($realInterfaceName);
    }

    public function getExtension(): ?CoreReflectionExtension
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function getExtensionName()
    {
        return $this->betterReflectionObject->getExtensionName() ?? false;
    }

    public function inNamespace(): bool
    {
        return $this->betterReflectionObject->inNamespace();
    }

    public function getNamespaceName(): string
    {
        return $this->betterReflectionObject->getNamespaceName();
    }

    public function getShortName(): string
    {
        return $this->betterReflectionObject->getShortName();
    }

    public function isAnonymous(): bool
    {
        return $this->betterReflectionObject->isAnonymous();
    }

    /**
     * @param class-string|null $name
     *
     * @return list<ReflectionAttribute|FakeReflectionAttribute>
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if ($flags !== 0 && $flags !== ReflectionAttribute::IS_INSTANCEOF) {
            throw new ValueError('Argument #2 ($flags) must be a valid attribute filter flag');
        }

        if ($name !== null && $flags !== 0) {
            $attributes = $this->betterReflectionObject->getAttributesByInstance($name);
        } elseif ($name !== null) {
            $attributes = $this->betterReflectionObject->getAttributesByName($name);
        } else {
            $attributes = $this->betterReflectionObject->getAttributes();
        }

        return array_map(static function (BetterReflectionAttribute $betterReflectionAttribute) {
            return ReflectionAttributeFactory::create($betterReflectionAttribute);
        }, $attributes);
    }

    public function isEnum(): bool
    {
        return $this->betterReflectionObject->isEnum();
    }
}
