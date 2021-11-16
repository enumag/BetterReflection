<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\Type;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionClass as CoreReflectionClass;
use ReflectionException;
use Roave\BetterReflection\Identifier\Identifier;
use Roave\BetterReflection\Identifier\IdentifierType;
use Roave\BetterReflection\Reflection\Reflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Ast\Exception\ParseToAstFailure;
use Roave\BetterReflection\SourceLocator\Ast\Strategy\NodeToReflection;
use Roave\BetterReflection\SourceLocator\Exception\EvaledAnonymousClassCannotBeLocated;
use Roave\BetterReflection\SourceLocator\Exception\NoAnonymousClassOnLine;
use Roave\BetterReflection\SourceLocator\Exception\TwoAnonymousClassesOnSameLine;
use Roave\BetterReflection\SourceLocator\FileChecker;
use Roave\BetterReflection\SourceLocator\Located\AnonymousLocatedSource;
use Roave\BetterReflection\Util\FileHelper;

use function array_filter;
use function array_values;
use function assert;
use function file_get_contents;
use function strpos;

/**
 * @internal
 */
final class AnonymousClassObjectSourceLocator implements SourceLocator
{
    private CoreReflectionClass $coreClassReflection;

    /**
     * @throws ReflectionException
     */
    public function __construct(object $anonymousClassObject, private Parser $parser)
    {
        $this->coreClassReflection = new CoreReflectionClass($anonymousClassObject);
    }

    /**
     * @template TType of ReflectionClass
	 * @param Identifier<TType> $identifier
	 * @return TType|null
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?ReflectionClass
    {
        return $this->getReflectionClass($reflector, $identifier->getType());
    }

	/**
	 * Find all identifiers of a type
	 *
	 * @template TType of ReflectionClass
	 * @param IdentifierType<TType> $identifierType
	 * @return list<TType>
	 *
	 * @throws ParseToAstFailure
	 */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return array_filter([$this->getReflectionClass($reflector, $identifierType)]);
    }

	/**
	 * @template TType of ReflectionClass
	 * @param Reflector $reflector
	 * @param IdentifierType<TType> $identifierType
	 * @return TType|null
	 */
    private function getReflectionClass(Reflector $reflector, IdentifierType $identifierType): ?ReflectionClass
    {
        if (! $identifierType->isClass()) {
            return null;
        }

        if (! $this->coreClassReflection->isAnonymous()) {
            return null;
        }

        $fileName = $this->coreClassReflection->getFileName();

        if (strpos($fileName, 'eval()\'d code') !== false) {
            throw EvaledAnonymousClassCannotBeLocated::create();
        }

        FileChecker::assertReadableFile($fileName);

        $fileName = FileHelper::normalizeWindowsPath($fileName);

        $nodeVisitor = new class ($fileName, $this->coreClassReflection->getStartLine()) extends NodeVisitorAbstract
        {
            /** @var list<Class_> */
            private array $anonymousClassNodes = [];

            public function __construct(private string $fileName, private int $startLine)
            {
            }

            /**
             * {@inheritDoc}
             */
            public function enterNode(Node $node)
            {
                if (! ($node instanceof Node\Stmt\Class_) || $node->name !== null) {
                    return null;
                }

                $this->anonymousClassNodes[] = $node;

                return null;
            }

            public function getAnonymousClassNode(): Class_
            {
                /** @var list<Class_> $anonymousClassNodesOnSameLine */
                $anonymousClassNodesOnSameLine = array_values(array_filter($this->anonymousClassNodes, fn (Class_ $node): bool => $node->getLine() === $this->startLine));

                if (! $anonymousClassNodesOnSameLine) {
                    throw NoAnonymousClassOnLine::create($this->fileName, $this->startLine);
                }

                if (isset($anonymousClassNodesOnSameLine[1])) {
                    throw TwoAnonymousClassesOnSameLine::create($this->fileName, $this->startLine);
                }

                return $anonymousClassNodesOnSameLine[0];
            }
        };

        $fileContents = file_get_contents($fileName);
        /** @var list<Node\Stmt> $ast */
        $ast = $this->parser->parse($fileContents);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NameResolver());
        $nodeTraverser->addVisitor($nodeVisitor);
        $nodeTraverser->traverse($ast);

        $reflectionClass = (new NodeToReflection())->__invoke(
            $reflector,
            $nodeVisitor->getAnonymousClassNode(),
            new AnonymousLocatedSource($fileContents, $fileName),
            null,
        );
        assert($reflectionClass instanceof ReflectionClass);

        return $reflectionClass;
    }
}
