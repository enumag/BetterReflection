<?php

declare(strict_types=1);

namespace Roave\BetterReflection\Reflection;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\ClassConst;
use ReflectionClassConstant as CoreReflectionClassConstant;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\NodeCompiler\CompiledValue;
use Roave\BetterReflection\NodeCompiler\CompileNodeToValue;
use Roave\BetterReflection\NodeCompiler\CompilerContext;
use Roave\BetterReflection\Reflection\Annotation\AnnotationHelper;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\Reflection\StringCast\ReflectionClassConstantStringCast;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\Util\CalculateReflectionColumn;
use Roave\BetterReflection\Util\Exception\NoNodePosition;
use Roave\BetterReflection\Util\GetLastDocComment;

class ReflectionClassConstant
{
    public const IS_FINAL = 32;

    /**
     * @var \Roave\BetterReflection\NodeCompiler\CompiledValue|null
     */
    private $compiledValue;

    /** @var list<ReflectionAttribute> */
    private $attributes;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \PhpParser\Node\Expr
     */
    private $valueExpr;

    /**
     * @var bool
     */
    private $isPublic;

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
    private $isFinal;

    /**
     * @var string
     */
    private $docComment;

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
     * @var \Roave\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionClass
     */
    private $owner;
    /**
     * @var int
     */
    private $positionInNode;
    private function __construct(Reflector $reflector, ClassConst $node, ReflectionClass $owner, int $positionInNode)
    {
        $this->reflector = $reflector;
        $this->owner = $owner;
        $this->positionInNode = $positionInNode;
        $this->attributes = ReflectionAttributeHelper::createAttributes($this->reflector, $this, $node->attrGroups);
        $this->name = $node->consts[$this->positionInNode]->name->name;
        $this->valueExpr = $node->consts[$this->positionInNode]->value;
        $this->isPublic = $node->isPublic();
        $this->isPrivate = $node->isPrivate();
        $this->isProtected = $node->isProtected();
        $this->isFinal = $this->isFinalInternal($node);
        $this->docComment = GetLastDocComment::forNode($node);
        $this->startLine = $node->getStartLine();
        $this->endLine = $node->getEndLine();
        try {
            $this->startColumn = CalculateReflectionColumn::getStartColumn($this->owner->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->startColumn = -1;
        }
        try {
            $this->endColumn = CalculateReflectionColumn::getEndColumn($this->owner->getLocatedSource()->getSource(), $node);
        } catch (NoNodePosition $e) {
            $this->endColumn = -1;
        }
    }
    /**
     * Create a reflection of a class's constant by Const Node
     *
     * @internal
     */
    public static function createFromNode(Reflector $reflector, ClassConst $node, int $positionInNode, ReflectionClass $owner): self
    {
        return new self($reflector, $node, $owner, $positionInNode);
    }

    /**
     * Get the name of the reflection (e.g. if this is a ReflectionClass this
     * will be the class name).
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @deprecated Use getValueExpr()
     * Returns constant value
     * @return mixed
     */
    public function getValue()
    {
        if ($this->compiledValue === null) {
            $this->compiledValue = (new CompileNodeToValue())->__invoke($this->valueExpr, new CompilerContext($this->reflector, $this));
        }

        return $this->compiledValue->value;
    }

    public function getValueExpr(): Expr
    {
        return $this->valueExpr;
    }

    /**
     * Constant is public
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * Constant is private
     */
    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    /**
     * Constant is protected
     */
    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function isFinalInternal(ClassConst $node): bool
    {
        $final = $node->isFinal();
        if ($final) {
            return true;
        }

        if (BetterReflection::$phpVersion >= 80100) {
            return false;
        }

        return $this->getDeclaringClass()->isInterface();
    }

    /**
     * Returns a bitfield of the access modifiers for this constant
     */
    public function getModifiers(): int
    {
        $val  = $this->isPublic() ? CoreReflectionClassConstant::IS_PUBLIC : 0;
        $val += $this->isProtected() ? CoreReflectionClassConstant::IS_PROTECTED : 0;
        $val += $this->isPrivate() ? CoreReflectionClassConstant::IS_PRIVATE : 0;
        $val += $this->isFinal() ? self::IS_FINAL : 0;

        return $val;
    }

    /**
     * Get the line number that this constant starts on.
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Get the line number that this constant ends on.
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
     * Get the declaring class
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return $this->owner;
    }

    /**
     * Returns the doc comment for this constant
     */
    public function getDocComment(): string
    {
        return $this->docComment;
    }

    public function isDeprecated(): bool
    {
        return AnnotationHelper::isDeprecated($this->getDocComment());
    }

    public function __toString(): string
    {
        return ReflectionClassConstantStringCast::toString($this);
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
}
