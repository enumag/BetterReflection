<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use BackedEnum;
use OutOfBoundsException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Stmt\ClassConst as ConstNode;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_ as EnumNode;
use PhpParser\Node\Stmt\Interface_ as InterfaceNode;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use PhpParser\Node\Stmt\Trait_ as TraitNode;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass as CoreReflectionClass;
use ReflectionException;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\Annotation\AnnotationHelper;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\Reflection\Exception\ClassDoesNotExist;
use Roave\BetterReflection\Reflection\Exception\NoObjectProvided;
use Roave\BetterReflection\Reflection\Exception\NotAClassReflection;
use Roave\BetterReflection\Reflection\Exception\NotAnInterfaceReflection;
use Roave\BetterReflection\Reflection\Exception\NotAnObject;
use Roave\BetterReflection\Reflection\Exception\ObjectNotInstanceOfClass;
use Roave\BetterReflection\Reflection\Exception\PropertyDoesNotExist;
use Roave\BetterReflection\Reflection\Exception\Uncloneable;
use Roave\BetterReflection\Reflection\StringCast\ReflectionClassStringCast;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Located\InternalLocatedSource;
use Roave\BetterReflection\SourceLocator\Located\LocatedSource;
use Roave\BetterReflection\Util\CalculateReflectionColumn;
use Roave\BetterReflection\Util\Exception\NoNodePosition;
use Roave\BetterReflection\Util\GetLastDocComment;
use Stringable;
use Traversable;
use UnitEnum;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reverse;
use function array_slice;
use function array_values;
use function assert;
use function end;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function ltrim;
use function sha1;
use function sprintf;
use function strtolower;

class ReflectionClass implements Reflection
{
    public const ANONYMOUS_CLASS_NAME_PREFIX        = 'class@anonymous';
    public const ANONYMOUS_CLASS_NAME_PREFIX_REGEXP = '~^(?:class|[\w\\\\]+)@anonymous~';
    private const ANONYMOUS_CLASS_NAME_SUFFIX       = '@anonymous';

    /** @var array{aliases: array<string, string>, modifiers: array<string, int>, precedences: array<string, string>}|null */
    private $cachedTraitsData;

    /** @var array<lowercase-string, ReflectionMethod>|null */
    private $cachedMethods;

    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionClass|null
     */
    private $cachedParentClass;

    /** @var array<string, ReflectionProperty>|null */
    private $cachedProperties;

    /** @var array<class-string, ReflectionClass>|null */
    private $cachedInterfaces;

    /** @var list<class-string>|null */
    private $cachedInterfaceNames;

    /** @var list<ReflectionClass>|null */
    private $cachedTraits;

    /** @var self[]|null */
    private $cachedInheritanceClassHierarchy;

    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionMethod|null
     */
    private $cachedConstructor;

    /** @var class-string|trait-string|null */
    private $cachedName;

    /**
     * @var string|null
     */
    private $parentClassName;

    /** @var list<class-string> */
    private $interfaceExtendsNames;

    /** @var array<class-string, class-string> */
    private $implementsNames;

    /** @var array<class-string, class-string> */
    private $immediateInterfacesNames;

    /**
     * @var bool
     */
    private $inNamespace;

    /**
     * @var string
     */
    private $namespaceName;

    /**
     * @var string|null
     */
    private $nodeName;

    /**
     * @var string|null
     */
    private $namespacedName;

    /**
     * @var bool
     */
    private $isAnonymous;

    /**
     * @var int
     */
    private $startLine;

    /**
     * @var int
     */
    private $endLine;

    /**
     * @var bool
     */
    private $isAbstract;

    /**
     * @var bool
     */
    private $isFinal;

    /**
     * @var bool
     */
    private $isTrait;

    /**
     * @var bool
     */
    private $isInterface;

    /**
     * @var bool
     */
    private $isEnum;

    /** @var array<string, ReflectionProperty> */
    private $immediateProperties;

    /** @var array<string, ReflectionClassConstant> */
    private $immediateReflectionConstants;

    /** @var array<int, ReflectionMethod> */
    private $immediateMethods;

    /**
     * @var int
     */
    private $startColumn;

    /**
     * @var int
     */
    private $endColumn;

    /**
     * @var string
     */
    private $docComment;

    /** @var list<string> */
    private $traitNames;

    /** @var list<ReflectionAttribute> */
    private $attributes;
    /**
     * @var \Roave\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \Roave\BetterReflection\SourceLocator\Located\LocatedSource
     */
    private $locatedSource;
    /**
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    protected function __construct(Reflector $reflector, $node, LocatedSource $locatedSource, ?NamespaceNode $declaringNamespace = null)
    {
        $this->reflector = $reflector;
        $this->locatedSource = $locatedSource;
        $this->isAnonymous = $node->name === null;
        $this->namespacedName = ($nodeNamespacedName = $node->namespacedName) ? $nodeNamespacedName->toString() : null;
        $this->nodeName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $this->endLine = $node->getEndLine();
        $this->startLine = $node->getStartLine();
        $this->isAbstract = $node instanceof ClassNode && $node->isAbstract();
        $this->isFinal = $node instanceof EnumNode ? true : ($node instanceof ClassNode && $node->isFinal());
        $this->isTrait = $node instanceof TraitNode;
        $this->isInterface = $node instanceof InterfaceNode;
        $this->isEnum = $node instanceof EnumNode;
        $this->parentClassName = $node instanceof ClassNode && $node->extends !== null ? $node->extends->toString() : null;
        $this->implementsNames = $this->getImplementsNames($node);
        $this->inNamespace = $declaringNamespace !== null
            && $declaringNamespace->name !== null;
        if ($node instanceof InterfaceNode) {
            $this->interfaceExtendsNames = $this->getInterfaceExtendsNamesInternal($node);
        }
        $this->immediateInterfacesNames = $this->getImmediateInterfacesNamesInternal($node);
        $this->namespaceName = $declaringNamespace !== null && $declaringNamespace->name ? implode('\\', $declaringNamespace->name->parts) : '';
        $this->immediateReflectionConstants = $this->getImmediateReflectionConstantsInternal($node);
        $this->immediateProperties = $this->getImmediatePropertiesInternal($node);
        $this->parseTraitUsages($node);
        $this->immediateMethods = $this->getImmediateMethodsInternal($node, $declaringNamespace);
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
        $this->docComment = GetLastDocComment::forNode($node);
        $this->traitNames = $this->getTraitNamesInternal($node);
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
    }

    public function __toString(): string
    {
        return ReflectionClassStringCast::toString($this);
    }

    /**
     * Create a ReflectionClass by name, using default reflectors etc.
     *
     * @deprecated Use Reflector instead.
     *
     * @throws IdentifierNotFound
     */
    public static function createFromName(string $className): self
    {
        return (new BetterReflection())->reflector()->reflectClass($className);
    }

    /**
     * Create a ReflectionClass from an instance, using default reflectors etc.
     *
     * This is simply a helper method that calls ReflectionObject::createFromInstance().
     *
     * @see ReflectionObject::createFromInstance
     *
     * @throws IdentifierNotFound
     * @throws ReflectionException
     */
    public static function createFromInstance(object $instance): self
    {
        return ReflectionObject::createFromInstance($instance);
    }

    /**
     * Create from a Class Node.
     *
     * @internal
     *
     * @param ClassNode|InterfaceNode|TraitNode|EnumNode $node      Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     * @param NamespaceNode|null                         $namespace optional - if omitted, we assume it is global namespaced class
     */
    public static function createFromNode(Reflector $reflector, $node, LocatedSource $locatedSource, ?NamespaceNode $namespace = null): self
    {
        return new self($reflector, $node, $locatedSource, $namespace);
    }

    /**
     * Get the "short" name of the class (e.g. for A\B\Foo, this will return
     * "Foo").
     */
    public function getShortName(): string
    {
        if (! $this->isAnonymous()) {
            return $this->nodeName;
        }

        $fileName = $this->getFileName();

        if ($fileName === null) {
            $fileName = sha1($this->locatedSource->getSource());
        }

        return sprintf('%s%s%c%s(%d)', $this->getAnonymousClassNamePrefix(), self::ANONYMOUS_CLASS_NAME_SUFFIX, "\0", $fileName, $this->getStartLine());
    }

    /**
     * PHP creates the name of the anonymous class based on first parent
     * or implemented interface.
     */
    private function getAnonymousClassNamePrefix(): string
    {
        $parentClassNames = $this->getParentClassNames();
        if ($parentClassNames !== []) {
            return $parentClassNames[0];
        }

        $interfaceNames = $this->getInterfaceNames();
        if ($interfaceNames !== []) {
            return $interfaceNames[0];
        }

        return 'class';
    }

    /**
     * Get the "full" name of the class (e.g. for A\B\Foo, this will return
     * "A\B\Foo").
     *
     * @return class-string|trait-string
     */
    public function getName(): string
    {
        if ($this->cachedName === null) {
            /** @psalm-var class-string|trait-string $name */
            $name = ! $this->inNamespace()
                ? $this->getShortName()
                : $this->namespacedName;

            $this->cachedName = $name;
        }

        return $this->cachedName;
    }

    /**
     * Get the "namespace" name of the class (e.g. for A\B\Foo, this will
     * return "A\B").
     */
    public function getNamespaceName(): string
    {
        return $this->namespaceName;
    }

    /**
     * Decide if this class is part of a namespace. Returns false if the class
     * is in the global namespace or does not have a specified namespace.
     *
     * @psalm-assert-if-true NamespaceNode $this->declaringNamespace
     * @psalm-assert-if-true Node\Name $this->declaringNamespace->name
     */
    public function inNamespace(): bool
    {
        return $this->inNamespace;
    }

    public function getExtensionName(): ?string
    {
        return $this->locatedSource->getExtensionName();
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function createMethodsFromTrait(ReflectionMethod $method): array
    {
        $traitAliases     = $this->getTraitAliases();
        $traitPrecedences = $this->getTraitPrecedences();
        $traitModifiers   = $this->getTraitModifiers();

        $methodHash = $this->methodHash($method->getImplementingClass()->getName(), $method->getName());

        $newFlags = $method->getFlags();
        if (array_key_exists($methodHash, $traitModifiers)) {
            $newFlags = ($newFlags & ~ Node\Stmt\Class_::VISIBILITY_MODIFIER_MASK) | $traitModifiers[$methodHash];
        }

        $createMethod = function (?string $aliasMethodName) use ($method, $newFlags) : ReflectionMethod {
            return $method->forTrait($this, $newFlags, $aliasMethodName);
        };

        $methods = [];
        foreach ($traitAliases as $aliasMethodName => $traitAliasDefinition) {
            if ($methodHash !== $traitAliasDefinition) {
                continue;
            }

            $methods[] = $createMethod($aliasMethodName);
        }

        if (! array_key_exists($methodHash, $traitPrecedences)) {
            $methods[] = $createMethod($method->getAliasName());
        }

        return $methods;
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function getParentMethods(): array
    {
        return array_merge([], ...array_map(function (ReflectionClass $ancestor): array {
            return array_map(function (ReflectionMethod $method) : ReflectionMethod {
                return $method->forClass($this);
            }, $ancestor->getMethods());
        }, array_filter([$this->getParentClass()])));
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function getMethodsFromTraits(): array
    {
        return array_merge([], ...array_map(function (ReflectionClass $trait): array {
            return array_merge([], ...array_map(function (ReflectionMethod $method) : array {
                return $this->createMethodsFromTrait($method);
            }, $trait->getMethods()));
        }, $this->getTraits()));
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function getMethodsFromInterfaces(): array
    {
        return array_merge([], ...array_map(static function (ReflectionClass $ancestor) : array {
            return $ancestor->getMethods();
        }, array_values($this->getInterfaces())));
    }

    /**
     * Construct a flat list of all methods in this precise order from:
     *  - current class
     *  - parent class
     *  - traits used in parent class
     *  - interfaces implemented in parent class
     *  - traits used in current class
     *  - interfaces implemented in current class
     *
     * Methods are not merged via their name as array index, since internal PHP method
     * sorting does not follow `\array_merge()` semantics.
     *
     * @return array<lowercase-string, ReflectionMethod> indexed by method name
     */
    private function getMethodsIndexedByName(): array
    {
        if ($this->cachedMethods !== null) {
            return $this->cachedMethods;
        }

        $classMethods     = $this->getImmediateMethods();
        $className        = $this->getName();
        $parentMethods    = $this->getParentMethods();
        $traitsMethods    = $this->getMethodsFromTraits();
        $interfaceMethods = $this->getMethodsFromInterfaces();

        $methods = [];

        foreach ([$classMethods, $parentMethods, 'traits' => $traitsMethods, $interfaceMethods] as $type => $typeMethods) {
            foreach ($typeMethods as $method) {
                $methodName = strtolower($method->getName());

                if (! array_key_exists($methodName, $methods)) {
                    $methods[$methodName] = $method;
                    continue;
                }

                if ($type !== 'traits') {
                    continue;
                }

                $existingMethod = $methods[$methodName];

                // Non-abstract trait method can overwrite existing method:
                // - when existing method comes from parent class
                // - when existing method comes from trait and is abstract

                if ($method->isAbstract()) {
                    continue;
                }

                if (
                    $existingMethod->getDeclaringClass()->getName() === $className
                    && ! (
                        $existingMethod->isAbstract()
                        && $existingMethod->getDeclaringClass()->isTrait()
                    )
                ) {
                    continue;
                }

                $methods[$methodName] = $method;
            }
        }

        $this->cachedMethods = $methods;

        return $this->cachedMethods;
    }

    /**
     * Fetch an array of all methods for this class.
     *
     * Filter the results to include only methods with certain attributes. Defaults
     * to no filtering.
     * Any combination of \ReflectionMethod::IS_STATIC,
     * \ReflectionMethod::IS_PUBLIC,
     * \ReflectionMethod::IS_PROTECTED,
     * \ReflectionMethod::IS_PRIVATE,
     * \ReflectionMethod::IS_ABSTRACT,
     * \ReflectionMethod::IS_FINAL.
     * For example if $filter = \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_FINAL
     * the only the final public methods will be returned
     *
     * @return list<ReflectionMethod>
     */
    public function getMethods(?int $filter = null): array
    {
        if ($filter === null) {
            return array_values($this->getMethodsIndexedByName());
        }

        return array_values(array_filter($this->getMethodsIndexedByName(), static function (ReflectionMethod $method) use ($filter) : bool {
            return (bool) ($filter & $method->getModifiers());
        }));
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    public function getImmediateMethods(?int $filter = null): array
    {
        $methods = $this->immediateMethods;
        $methodsByName = [];
        foreach ($methods as $method) {
            if ($filter !== null && ! ($filter & $method->getModifiers())) {
                continue;
            }

            if (array_key_exists($method->getName(), $methodsByName)) {
                continue;
            }

            $methodsByName[$method->getName()] = $method;
        }

        return $methodsByName;
    }

    /**
     * Get only the methods that this class implements (i.e. do not search
     * up parent classes etc.)
     *
     * @see ReflectionClass::getMethods for the usage of $filter
     *
     * @return array<int, ReflectionMethod>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getImmediateMethodsInternal($node, ?NamespaceNode $declaringNamespace): array
    {
        /** @var list<ReflectionMethod> $methods */
        $methods = array_map(function (ClassMethod $methodNode) use ($declaringNamespace) : ReflectionMethod {
            return ReflectionMethod::createFromNode($this->reflector, $methodNode, $this->locatedSource, $declaringNamespace, $this, $this, $this);
        }, $node->getMethods());

        return $this->addEnumMethods($methods, $node, $declaringNamespace);
    }

    /**
     * @param list<ReflectionMethod> $methods
     *
     * @return list<ReflectionMethod>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function addEnumMethods(array $methods, $node, ?NamespaceNode $declaringNamespace): array
    {
        if (! $node instanceof EnumNode) {
            return $methods;
        }

        $internalLocatedSource = new InternalLocatedSource('', $this->getName(), 'Core', $this->getFileName());
        $createMethod          = function (string $name, array $params, $returnType) use ($internalLocatedSource, $declaringNamespace) : ReflectionMethod {
            return ReflectionMethod::createFromNode($this->reflector, new ClassMethod(new Node\Identifier($name), [
                'flags' => ClassNode::MODIFIER_PUBLIC | ClassNode::MODIFIER_STATIC,
                'params' => $params,
                'returnType' => $returnType,
            ]), $internalLocatedSource, $declaringNamespace, $this, $this, $this);
        };

        $methods[] = $createMethod('cases', [], new Node\Identifier('array'));

        if ($node->scalarType === null) {
            return $methods;
        }

        $valueParameter = new Node\Param(new Node\Expr\Variable('value'), null, new Node\UnionType([new Node\Identifier('string'), new Node\Identifier('int')]));

        $methods[] = $createMethod('from', [$valueParameter], new Node\Identifier('static'));

        $methods[] = $createMethod('tryFrom', [$valueParameter], new Node\NullableType(new Node\Identifier('static')));

        return $methods;
    }

    /**
     * Get a single method with the name $methodName.
     *
     * @throws OutOfBoundsException
     */
    public function getMethod(string $methodName): ReflectionMethod
    {
        $lowercaseMethodName = strtolower($methodName);
        $methods             = $this->getMethodsIndexedByName();

        if (! isset($methods[$lowercaseMethodName])) {
            throw new OutOfBoundsException(sprintf('Could not find method: %s', $methodName));
        }

        return $methods[$lowercaseMethodName];
    }

    /**
     * Does the class have the specified method method?
     */
    public function hasMethod(string $methodName): bool
    {
        try {
            $this->getMethod($methodName);

            return true;
        } catch (OutOfBoundsException $exception) {
            return false;
        }
    }

    /**
     * Get an associative array of only the constants for this specific class (i.e. do not search
     * up parent classes etc.), with keys as constant names and values as constant values.
     *
     * @return array<string, mixed>
     */
    public function getImmediateConstants(): array
    {
        return array_map(static function (ReflectionClassConstant $classConstant) {
            return $classConstant->getValue();
        }, $this->getImmediateReflectionConstants());
    }

    /**
     * Get an associative array of the defined constants in this class,
     * with keys as constant names and values as constant values.
     *
     * @return array<string, mixed>
     */
    public function getConstants(): array
    {
        return array_map(static function (ReflectionClassConstant $classConstant) {
            return $classConstant->getValue();
        }, $this->getReflectionConstants());
    }

    /**
     * Get the value of the specified class constant.
     *
     * Returns null if not specified.
     *
     * @return mixed[]|bool|float|int|string|null
     */
    public function getConstant(string $name)
    {
        $reflectionConstant = $this->getReflectionConstant($name);

        if ($reflectionConstant === null) {
            return null;
        }

        /** @psalm-var scalar|array<scalar>|null $constantValue */
        $constantValue = $reflectionConstant->getValue();

        return $constantValue;
    }

    /**
     * Does this class have the specified constant?
     */
    public function hasConstant(string $name): bool
    {
        return $this->getReflectionConstant($name) !== null;
    }

    /**
     * Get the reflection object of the specified class constant.
     *
     * Returns null if not specified.
     */
    public function getReflectionConstant(string $name): ?ReflectionClassConstant
    {
        return $this->getReflectionConstants()[$name] ?? null;
    }

    /**
     * Get an associative array of only the constants for this specific class (i.e. do not search
     * up parent classes etc.), with keys as constant names and values as {@see ReflectionClassConstant} objects.
     *
     * @return array<string, ReflectionClassConstant> indexed by name
     */
    public function getImmediateReflectionConstants(): array
    {
        return $this->immediateReflectionConstants;
    }

    /**
     * @return array<string, ReflectionClassConstant> indexed by name
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getImmediateReflectionConstantsInternal($node): array
    {
        $constants = array_merge([], ...array_map(function (ConstNode $constNode): array {
            $constants = [];

            foreach (array_keys($constNode->consts) as $constantPositionInNode) {
                assert(is_int($constantPositionInNode));
                $constants[] = ReflectionClassConstant::createFromNode($this->reflector, $constNode, $constantPositionInNode, $this);
            }

            return $constants;
        }, array_filter($node->stmts, static function (Node\Stmt $stmt) : bool {
            return $stmt instanceof ConstNode;
        })));

        return array_combine(array_map(static function (ReflectionClassConstant $constant) : string {
            return $constant->getName();
        }, $constants), $constants);
    }

    /**
     * Get an associative array of the defined constants in this class,
     * with keys as constant names and values as {@see ReflectionClassConstant} objects.
     *
     * @return array<string, ReflectionClassConstant> indexed by name
     */
    public function getReflectionConstants(): array
    {
        // Note: constants are not merged via their name as array index, since internal PHP constant
        //       sorting does not follow `\array_merge()` semantics
        $allReflectionConstants = array_merge(array_values($this->getImmediateReflectionConstants()), ...array_map(static function (ReflectionClass $ancestor): array {
            return array_values(array_filter($ancestor->getReflectionConstants(), static function (ReflectionClassConstant $classConstant) : bool {
                return ! $classConstant->isPrivate();
            }));
        }, array_filter([$this->getParentClass()])), ...array_map(static function (ReflectionClass $interface) : array {
            return array_values($interface->getReflectionConstants());
        }, array_values($this->getInterfaces())));

        $reflectionConstants = [];

        foreach ($allReflectionConstants as $constant) {
            $constantName = $constant->getName();

            if (isset($reflectionConstants[$constantName])) {
                continue;
            }

            $reflectionConstants[$constantName] = $constant;
        }

        return $reflectionConstants;
    }

    /**
     * Get the constructor method for this class.
     *
     * @throws OutOfBoundsException
     */
    public function getConstructor(): ReflectionMethod
    {
        if ($this->cachedConstructor !== null) {
            return $this->cachedConstructor;
        }

        $constructors = array_values(array_filter($this->getMethods(), static function (ReflectionMethod $method) : bool {
            return $method->isConstructor();
        }));

        if (! isset($constructors[0])) {
            throw new OutOfBoundsException('Could not find method: __construct');
        }

        return $this->cachedConstructor = $constructors[0];
    }

    /**
     * Get only the properties for this specific class (i.e. do not search
     * up parent classes etc.)
     *
     * @see ReflectionClass::getProperties() for the usage of filter
     *
     * @return array<string, ReflectionProperty>
     */
    public function getImmediateProperties(?int $filter = null): array
    {
        if ($filter === null) {
            return $this->immediateProperties;
        }

        return array_filter($this->immediateProperties, static function (ReflectionProperty $property) use ($filter) : bool {
            return (bool) ($filter & $property->getModifiers());
        });
    }

    /**
     * @return array<string, ReflectionProperty>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getImmediatePropertiesInternal($node): array
    {
        $properties = [];
        foreach ($node->getProperties() as $propertiesNode) {
            foreach (array_keys($propertiesNode->props) as $propertyPositionInNode) {
                assert(is_int($propertyPositionInNode));
                $property                         = ReflectionProperty::createFromNode($this->reflector, $propertiesNode, $propertyPositionInNode, $this, $this, false);
                $properties[$property->getName()] = $property;
            }
        }

        foreach ($node->getMethods() as $methodNode) {
            if ($methodNode->name->toLowerString() !== '__construct') {
                continue;
            }

            foreach ($methodNode->params as $parameterNode) {
                if ($parameterNode->flags === 0) {
                    // No flags, no promotion
                    continue;
                }

                $parameterNameNode = $parameterNode->var;
                assert($parameterNameNode instanceof Node\Expr\Variable);
                assert(is_string($parameterNameNode->name));

                $propertyNode                     = new Node\Stmt\Property($parameterNode->flags, [new Node\Stmt\PropertyProperty($parameterNameNode->name)], $parameterNode->getAttributes(), $parameterNode->type, $parameterNode->attrGroups);
                $property                         = ReflectionProperty::createFromNode($this->reflector, $propertyNode, 0, $this, $this, true);
                $properties[$property->getName()] = $property;
            }
        }

        return $this->addEnumProperties($properties, $node);
    }

    /**
     * @param array<string, ReflectionProperty> $properties
     *
     * @return array<string, ReflectionProperty>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function addEnumProperties(array $properties, $node): array
    {
        $createProperty = function (string $name, $type): ReflectionProperty {
            $propertyNode = new Node\Stmt\Property(ClassNode::MODIFIER_PUBLIC | ClassNode::MODIFIER_READONLY, [new Node\Stmt\PropertyProperty($name)], [], $type);

            return ReflectionProperty::createFromNode($this->reflector, $propertyNode, 0, $this, $this, false);
        };

        if (! $node instanceof EnumNode) {
            if ($node instanceof Node\Stmt\Interface_) {
                $interfaceName = $this->getName();
                if ($interfaceName === 'UnitEnum') {
                    $properties['name'] = $createProperty('name', 'string');
                }

                if ($interfaceName === 'BackedEnum') {
                    $properties['value'] = $createProperty('value', new Node\UnionType([
                        new Node\Identifier('int'),
                        new Node\Identifier('string'),
                    ]));
                }
            }

            return $properties;
        }

        $properties['name'] = $createProperty('name', 'string');

        if ($node->scalarType !== null) {
            $properties['value'] = $createProperty('value', $node->scalarType);
        }

        return $properties;
    }

    /**
     * Get the properties for this class.
     *
     * Filter the results to include only properties with certain attributes. Defaults
     * to no filtering.
     * Any combination of \ReflectionProperty::IS_STATIC,
     * \ReflectionProperty::IS_PUBLIC,
     * \ReflectionProperty::IS_PROTECTED,
     * \ReflectionProperty::IS_PRIVATE.
     * For example if $filter = \ReflectionProperty::IS_STATIC | \ReflectionProperty::IS_PUBLIC
     * only the static public properties will be returned
     *
     * @return array<string, ReflectionProperty>
     */
    public function getProperties(?int $filter = null): array
    {
        if ($this->cachedProperties === null) {
            // merging together properties from parent class, traits, current class (in this precise order)
            $this->cachedProperties = array_merge(array_merge([], ...array_map(static function (ReflectionClass $ancestor) use ($filter): array {
                return array_filter($ancestor->getProperties($filter), static function (ReflectionProperty $property) : bool {
                    return ! $property->isPrivate();
                });
            }, array_merge(array_filter([$this->getParentClass()]), array_values($this->getInterfaces()))), ...array_map(function (ReflectionClass $trait) use ($filter) {
                return array_map(function (ReflectionProperty $property) : ReflectionProperty {
                    return $property->forClass($this);
                }, $trait->getProperties($filter));
            }, $this->getTraits())), $this->getImmediateProperties());
        }

        if ($filter === null) {
            return $this->cachedProperties;
        }

        return array_filter($this->cachedProperties, static function (ReflectionProperty $property) use ($filter) : bool {
            return (bool) ($filter & $property->getModifiers());
        });
    }

    /**
     * Get the property called $name.
     *
     * Returns null if property does not exist.
     */
    public function getProperty(string $name): ?ReflectionProperty
    {
        $properties = $this->getProperties();

        if (! isset($properties[$name])) {
            return null;
        }

        return $properties[$name];
    }

    /**
     * Does this class have the specified property?
     */
    public function hasProperty(string $name): bool
    {
        return $this->getProperty($name) !== null;
    }

    /**
     * @return array<string, scalar|array<scalar>|null>
     */
    public function getDefaultProperties(): array
    {
        return array_map(static function (ReflectionProperty $property) {
            return $property->getDefaultValue();
        }, $this->getProperties());
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
     * Get the line number that this class starts on.
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Get the line number that this class ends on.
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
     * Get the parent class, if it is defined. If this class does not have a
     * specified parent class, this will throw an exception.
     *
     * @throws NotAClassReflection
     */
    public function getParentClass(): ?ReflectionClass
    {
        if ($this->parentClassName === null) {
            return null;
        }

        if ($this->cachedParentClass !== null) {
            return $this->cachedParentClass;
        }

        try {
            $parentClass = $this->reflector->reflectClass($this->parentClassName);
        } catch (IdentifierNotFound $exception) {
            return null;
        }

        if ($parentClass->isInterface() || $parentClass->isTrait()) {
            throw NotAClassReflection::fromReflectionClass($parentClass);
        }

        return $this->cachedParentClass = $parentClass;
    }

    /**
     * Gets the parent class names.
     *
     * @return list<class-string> A numerical array with parent class names as the values.
     */
    public function getParentClassNames(): array
    {
        return array_map(static function (self $parentClass) : string {
            return $parentClass->getName();
        }, array_slice(array_reverse($this->getInheritanceClassHierarchy()), 1));
    }

    public function getDocComment(): string
    {
        return $this->docComment;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    /**
     * Is this an internal class?
     */
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

    public function isDeprecated(): bool
    {
        return AnnotationHelper::isDeprecated($this->getDocComment());
    }

    /**
     * Is this class an abstract class.
     */
    public function isAbstract(): bool
    {
        return $this->isAbstract;
    }

    /**
     * Is this class a final class.
     */
    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    /**
     * Get the core-reflection-compatible modifier values.
     */
    public function getModifiers(): int
    {
        $val  = $this->isAbstract() ? CoreReflectionClass::IS_EXPLICIT_ABSTRACT : 0;
        $val += $this->isFinal() ? CoreReflectionClass::IS_FINAL : 0;

        return $val;
    }

    /**
     * Is this reflection a trait?
     */
    public function isTrait(): bool
    {
        return $this->isTrait;
    }

    /**
     * Is this reflection an interface?
     */
    public function isInterface(): bool
    {
        return $this->isInterface;
    }

    /**
     * Get the traits used, if any are defined. If this class does not have any
     * defined traits, this will return an empty array.
     *
     * @return list<ReflectionClass>
     */
    public function getTraits(): array
    {
        if ($this->cachedTraits !== null) {
            return $this->cachedTraits;
        }

        $traits = [];
        foreach ($this->traitNames as $traitName) {
            try {
                $traits[] = $this->reflector->reflectClass($traitName);
            } catch (IdentifierNotFound $exception) {
                // pass
            }
        }

        return $this->cachedTraits = $traits;
    }

    /**
     * @return list<string>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getTraitNamesInternal($node): array
    {
        return array_values(array_map(function (Node\Name $importedTrait) : string {
            return $importedTrait->toString();
        }, array_merge([], ...array_map(static function (TraitUse $traitUse) : array {
            return $traitUse->traits;
        }, array_filter($node->stmts, static function (Node $node) : bool {
            return $node instanceof TraitUse;
        })))));
    }

    /**
     * @param array<class-string, class-string> $interfaces
     *
     * @return array<class-string, class-string>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function addStringableInterface(array $interfaces, $node): array
    {
        if (BetterReflection::$phpVersion < 80000) {
            return $interfaces;
        }

        /** @psalm-var class-string $stringableClassName */
        $stringableClassName = Stringable::class;

        if ($node->namespacedName !== null && $node->namespacedName->toString() === $stringableClassName) {
            return $interfaces;
        }

        if (array_key_exists($stringableClassName, $interfaces)) {
            return $interfaces;
        }

        foreach ($node->getMethods() as $methodNode) {
            if ($methodNode->name->toLowerString() === '__tostring') {
                $interfaces[$stringableClassName] = $stringableClassName;
                break;
            }
        }

        return $interfaces;
    }

    /**
     * @param array<class-string, class-string> $interfaces
     *
     * @return array<class-string, class-string>
     */
    private function addEnumInterfaces(array $interfaces, EnumNode $node): array
    {
        $interfaces[UnitEnum::class] = UnitEnum::class;

        if ($node->scalarType !== null) {
            $interfaces[BackedEnum::class] = BackedEnum::class;
        }

        return $interfaces;
    }

    /**
     * Get the names of the traits used as an array of strings, if any are
     * defined. If this class does not have any defined traits, this will
     * return an empty array.
     *
     * @return list<trait-string>
     */
    public function getTraitNames(): array
    {
        return array_map(static function (ReflectionClass $trait): string {
            /** @psalm-var trait-string $traitName */
            $traitName = $trait->getName();

            return $traitName;
        }, $this->getTraits());
    }

    /**
     * Return a list of the aliases used when importing traits for this class.
     * The returned array is in key/value pair in this format:.
     *
     *   'aliasedMethodName' => 'ActualClass::actualMethod'
     *
     * @return array<string, string>
     *
     * @example
     * // When reflecting a class such as:
     * class Foo
     * {
     *     use MyTrait {
     *         myTraitMethod as myAliasedMethod;
     *     }
     * }
     * // This method would return
     * //   ['myAliasedMethod' => 'MyTrait::myTraitMethod']
     */
    public function getTraitAliases(): array
    {
        assert($this->cachedTraitsData !== null);

        return $this->cachedTraitsData['aliases'];
    }

    /**
     * Return a list of the precedences used when importing traits for this class.
     * The returned array is in key/value pair in this format:.
     *
     *   'Class::method' => 'Class::method'
     *
     * @return array<string, string>
     *
     * @example
     * // When reflecting a class such as:
     * class Foo
     * {
     *     use MyTrait, MyTrait2 {
     *         MyTrait2::foo insteadof MyTrait1;
     *     }
     * }
     * // This method would return
     * //   ['MyTrait1::foo' => 'MyTrait2::foo']
     */
    private function getTraitPrecedences(): array
    {
        assert($this->cachedTraitsData !== null);

        return $this->cachedTraitsData['precedences'];
    }

    /**
     * Return a list of the used modifiers when importing traits for this class.
     * The returned array is in key/value pair in this format:.
     *
     *   'methodName' => 'modifier'
     *
     * @return array<string, int>
     *
     * @example
     * // When reflecting a class such as:
     * class Foo
     * {
     *     use MyTrait {
     *         myTraitMethod as public;
     *     }
     * }
     * // This method would return
     * //   ['myTraitMethod' => 1]
     */
    private function getTraitModifiers(): array
    {
        assert($this->cachedTraitsData !== null);

        return $this->cachedTraitsData['modifiers'];
    }

    /**
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function parseTraitUsages($node): void
    {
        $traitUsages = array_filter($node->stmts, static function (Node $node) : bool {
            return $node instanceof TraitUse;
        });

        $this->cachedTraitsData = [
            'aliases'     => [],
            'modifiers'   => [],
            'precedences' => [],
        ];

        foreach ($traitUsages as $traitUsage) {
            $traitNames  = $traitUsage->traits;
            $adaptations = $traitUsage->adaptations;

            foreach ($adaptations as $adaptation) {
                $usedTrait = $adaptation->trait;
                if ($usedTrait === null) {
                    $usedTrait = end($traitNames);
                }

                $methodHash = $this->methodHash($usedTrait->toString(), $adaptation->method->toString());

                if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {
                    if ($adaptation->newModifier) {
                        $this->cachedTraitsData['modifiers'][$methodHash] = $adaptation->newModifier;
                    }

                    if ($adaptation->newName) {
                        $this->cachedTraitsData['aliases'][$adaptation->newName->name] = $methodHash;
                        continue;
                    }
                }

                if (! $adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence || ! $adaptation->insteadof) {
                    continue;
                }

                foreach ($adaptation->insteadof as $insteadof) {
                    $adaptationNameHash = $this->methodHash($insteadof->toString(), $adaptation->method->toString());
                    $originalNameHash   = $methodHash;

                    $this->cachedTraitsData['precedences'][$adaptationNameHash] = $originalNameHash;
                }
            }
        }
    }

    /**
     * @psalm-pure
     */
    private function methodHash(string $className, string $methodName): string
    {
        return sprintf('%s::%s', $className, strtolower($methodName));
    }

    /**
     * Gets the interfaces.
     *
     * @link https://php.net/manual/en/reflectionclass.getinterfaces.php
     *
     * @return array<class-string, ReflectionClass> An associative array of interfaces, with keys as interface names and the array
     *                                        values as {@see ReflectionClass} objects.
     */
    public function getInterfaces(): array
    {
        if ($this->cachedInterfaces !== null) {
            return $this->cachedInterfaces;
        }

        return $this->cachedInterfaces = array_merge(...array_map(static function (self $reflectionClass) : array {
            return $reflectionClass->getCurrentClassImplementedInterfacesIndexedByName();
        }, $this->getInheritanceClassHierarchy()));
    }

    /**
     * @return array<class-string, self>
     */
    private function getCurrentClassImplementedInterfacesIndexedByName(): array
    {
        if ($this->isTrait()) {
            return [];
        }

        if ($this->isInterface()) {
            // assumption: first key is the current interface
            return array_slice($this->getInterfacesHierarchy(), 1);
        }

        $interfaces = [];
        foreach ($this->implementsNames as $name) {
            try {
                $interface = $this->reflector->reflectClass($name);
                foreach ($interface->getInterfacesHierarchy() as $n => $i) {
                    $interfaces[$n] = $i;
                }
            } catch (IdentifierNotFound $e) {
                continue;
            }
        }

        return $interfaces;
    }

    /**
     * @param ClassNode|InterfaceNode|TraitNode|EnumNode $node
     * @return array<class-string, class-string>
     */
    private function getImplementsNames($node): array
    {
        if (!$node instanceof ClassNode && !$node instanceof EnumNode) {
            return [];
        }
        $names = [];
        foreach ($node->implements as $implement) {
            $names[$implement->toString()] = $implement->toString();
        }

        /** @var array<class-string, class-string> $names */

        if ($node instanceof EnumNode) {
            $names = $this->addEnumInterfaces($names, $node);
        }

        return $this->addStringableInterface($names, $node);
    }

    /**
     * This method allows us to retrieve all interfaces parent of the this interface. Do not use on class nodes!
     *
     * @return array<class-string, ReflectionClass> parent interfaces of this interface
     *
     * @throws NotAnInterfaceReflection
     */
    private function getInterfacesHierarchy(): array
    {
        if (!$this->isInterface()) {
            throw NotAnInterfaceReflection::fromReflectionClass($this);
        }

        $interfaces = [$this->getName() => $this];
        foreach ($this->interfaceExtendsNames as $interfaceName) {
            try {
                $interface = $this->reflector->reflectClass($interfaceName);
                foreach ($interface->getInterfacesHierarchy() as $hName => $hInterface) {
                    $interfaces[$hName] = $hInterface;
                }
            } catch (IdentifierNotFound $exception) {
                continue;
            }
        }

        return $interfaces;
    }

    /**
     * This method allows us to retrieve all interfaces parent of the this interface. Do not use on class nodes!
     *
     * @return list<class-string> parent interfaces of this interface
     *
     * @throws NotAnInterfaceReflection
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getInterfaceExtendsNamesInternal($node): array
    {
        if (! $node instanceof InterfaceNode) {
            throw NotAnInterfaceReflection::fromReflectionClass($this);
        }

        /** @var array<class-string, class-string> $interfaces */
        $interfaces = array_merge(array_map(function (Node\Name $interfaceName) : string {
            return $interfaceName->toString();
        }, $node->extends));

        return array_values($this->addStringableInterface($interfaces, $node));
    }

    /**
     * Get only the interfaces that this class implements (i.e. do not search
     * up parent classes etc.)
     *
     * @return array<string, ReflectionClass>
     */
    public function getImmediateInterfaces(): array
    {
        $interfaces = [];
        foreach ($this->immediateInterfacesNames as $interfaceName) {
            try {
                $interfaces[$interfaceName] = $this->reflector->reflectClass($interfaceName);
            } catch (IdentifierNotFound $e) {
                continue;
            }
        }

        return $interfaces;
    }

    /**
     * @return array<class-string, class-string>
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     */
    private function getImmediateInterfacesNamesInternal($node): array
    {
        if ($node instanceof TraitNode) {
            return [];
        }

        $names = array_map(static function (Node\Name $interfaceName) : string {
            return $interfaceName->toString();
        }, $node instanceof InterfaceNode ? $node->extends : $node->implements);

        /** @var array<class-string, class-string> $interfaces */
        $interfaces = array_combine($names, $names);

        if ($node instanceof EnumNode) {
            $interfaces = $this->addEnumInterfaces($interfaces, $node);
        }

        return $this->addStringableInterface($interfaces, $node);
    }

    /**
     * Gets the interface names.
     *
     * @link https://php.net/manual/en/reflectionclass.getinterfacenames.php
     *
     * @return list<class-string> A numerical array with interface names as the values.
     */
    public function getInterfaceNames(): array
    {
        if ($this->cachedInterfaceNames !== null) {
            return $this->cachedInterfaceNames;
        }

        return $this->cachedInterfaceNames = array_values(array_map(static function (self $interface) : string {
            return $interface->getName();
        }, $this->getInterfaces()));
    }

    /**
     * Checks whether the given object is an instance.
     *
     * @link https://php.net/manual/en/reflectionclass.isinstance.php
     */
    public function isInstance(object $object): bool
    {
        $className = $this->getName();

        // note: since $object was loaded, we can safely assume that $className is available in the current
        //       php script execution context
        return $object instanceof $className;
    }

    /**
     * Checks whether the given class string is a subclass of this class.
     *
     * @link https://php.net/manual/en/reflectionclass.isinstance.php
     */
    public function isSubclassOf(string $className): bool
    {
        return in_array(ltrim($className, '\\'), array_map(static function (self $reflectionClass) : string {
            return $reflectionClass->getName();
        }, array_slice(array_reverse($this->getInheritanceClassHierarchy()), 1)), true);
    }

    /**
     * Checks whether this class implements the given interface.
     *
     * @link https://php.net/manual/en/reflectionclass.implementsinterface.php
     */
    public function implementsInterface(string $interfaceName): bool
    {
        return in_array(ltrim($interfaceName, '\\'), $this->getInterfaceNames(), true);
    }

    /**
     * Checks whether this reflection is an instantiable class
     *
     * @link https://php.net/manual/en/reflectionclass.isinstantiable.php
     */
    public function isInstantiable(): bool
    {
        // @TODO doesn't consider internal non-instantiable classes yet.

        if ($this->isAbstract()) {
            return false;
        }

        if ($this->isInterface()) {
            return false;
        }

        if ($this->isTrait()) {
            return false;
        }

        try {
            return $this->getConstructor()->isPublic();
        } catch (OutOfBoundsException $exception) {
            return true;
        }
    }

    /**
     * Checks whether this is a reflection of a class that supports the clone operator
     *
     * @link https://php.net/manual/en/reflectionclass.iscloneable.php
     */
    public function isCloneable(): bool
    {
        if (! $this->isInstantiable()) {
            return false;
        }

        if (! $this->hasMethod('__clone')) {
            return true;
        }

        return $this->getMethod('__clone')->isPublic();
    }

    /**
     * Checks if iterateable
     *
     * @link https://php.net/manual/en/reflectionclass.isiterateable.php
     */
    public function isIterateable(): bool
    {
        return $this->isInstantiable() && $this->implementsInterface(Traversable::class);
    }

    public function isEnum(): bool
    {
        return $this->isEnum;
    }

    /**
     * @return list<ReflectionClass> ordered from inheritance root to leaf (this class)
     */
    private function getInheritanceClassHierarchy(): array
    {
        if ($this->cachedInheritanceClassHierarchy === null) {
            $parentClass = $this->getParentClass();

            $this->cachedInheritanceClassHierarchy = $parentClass
                ? array_merge($parentClass->getInheritanceClassHierarchy(), [$this])
                : [$this];
        }

        return $this->cachedInheritanceClassHierarchy;
    }

    /**
     * @throws Uncloneable
     */
    public function __clone()
    {
        throw Uncloneable::fromClass(static::class);
    }

    /**
     * Get the value of a static property, if it exists. Throws a
     * PropertyDoesNotExist exception if it does not exist or is not static.
     * (note, differs very slightly from internal reflection behaviour)
     *
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws NotAnObject
     * @throws ObjectNotInstanceOfClass
     * @return mixed
     */
    public function getStaticPropertyValue(string $propertyName)
    {
        $property = $this->getProperty($propertyName);

        if (! $property || ! $property->isStatic()) {
            throw PropertyDoesNotExist::fromName($propertyName);
        }

        return $property->getValue();
    }

    /**
     * Set the value of a static property
     *
     * @throws ClassDoesNotExist
     * @throws NoObjectProvided
     * @throws NotAnObject
     * @throws ObjectNotInstanceOfClass
     * @param mixed $value
     */
    public function setStaticPropertyValue(string $propertyName, $value): void
    {
        $property = $this->getProperty($propertyName);

        if (! $property || ! $property->isStatic()) {
            throw PropertyDoesNotExist::fromName($propertyName);
        }

        $property->setValue($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStaticProperties(): array
    {
        $staticProperties = [];

        foreach ($this->getProperties() as $property) {
            if (! $property->isStatic()) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $staticProperties[$property->getName()] = $property->getValue();
        }

        return $staticProperties;
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
