<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\Reflection;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Stmt\Enum_ as EnumNode;
use PhpParser\Node\Stmt\Interface_ as InterfaceNode;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use PhpParser\Node\Stmt\Trait_ as TraitNode;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Located\LocatedSource;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function assert;

class ReflectionEnum extends ReflectionClass
{
    /** @var array<string, ReflectionEnumCase> */
    private $cases;

    /**
     * @var \PHPStan\BetterReflection\Reflection\ReflectionNamedType|null
     */
    private $backingType;
    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
     */
    protected function __construct(Reflector $reflector, EnumNode $node, LocatedSource $locatedSource, ?NamespaceNode $declaringNamespace = null)
    {
        $this->reflector = $reflector;
        parent::__construct($reflector, $node, $locatedSource, $declaringNamespace);
        $this->cases = $this->getCasesInternal($node);
        if ($node->scalarType !== null) {
            $backingType = ReflectionNamedType::createFromNode($this->reflector, $this, $node->scalarType);
            assert($backingType instanceof ReflectionNamedType);
            $this->backingType = $backingType;
        } else {
            $this->backingType = null;
        }
    }
    /**
     * @internal
     * @param ClassNode|EnumNode|InterfaceNode|TraitNode $node
     * @return $this
     */
    public static function createFromNode(Reflector $reflector, $node, LocatedSource $locatedSource, ?NamespaceNode $namespace = null): \PHPStan\BetterReflection\Reflection\ReflectionClass
    {
        $node = $node;
        assert($node instanceof EnumNode);
        return new self($reflector, $node, $locatedSource, $namespace);
    }

    public function hasCase(string $name): bool
    {
        $cases = $this->getCases();

        return array_key_exists($name, $cases);
    }

    public function getCase(string $name): ?ReflectionEnumCase
    {
        $cases = $this->getCases();

        return $cases[$name] ?? null;
    }

    /**
     * @return array<string, ReflectionEnumCase>
     */
    public function getCases(): array
    {
        return $this->cases;
    }

    /**
     * @return array<string, ReflectionEnumCase>
     */
    private function getCasesInternal(EnumNode $node): array
    {
        $casesNodes = array_filter($node->stmts, static function (Node\Stmt $stmt) : bool {
            return $stmt instanceof Node\Stmt\EnumCase;
        });

        return array_combine(array_map(static function (Node\Stmt\EnumCase $node) : string {
            return $node->name->toString();
        }, $casesNodes), array_map(function (Node\Stmt\EnumCase $node) : ReflectionEnumCase {
            return ReflectionEnumCase::createFromNode($this->reflector, $node, $this);
        }, $casesNodes));
    }

    public function isBacked(): bool
    {
        return $this->backingType !== null;
    }

    public function getBackingType(): ReflectionNamedType
    {
        if ($this->backingType === null) {
            throw new LogicException('This enum does not have a backing type available');
        }

        return $this->backingType;
    }
}
