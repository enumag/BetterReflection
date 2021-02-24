<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection;

use PhpParser\Lexer\Emulative;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\BetterReflection\Reflector\ClassReflector;
use PHPStan\BetterReflection\Reflector\ConstantReflector;
use PHPStan\BetterReflection\Reflector\FunctionReflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Locator as AstLocator;
use PHPStan\BetterReflection\SourceLocator\Ast\Parser\MemoizingParser;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\AggregateSourceStubber;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\PhpStormStubsSourceStubber;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\ReflectionSourceStubber;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\SourceStubber;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\EvaledCodeSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\SourceLocator;
use PHPStan\BetterReflection\Util\FindReflectionOnLine;
use const PHP_VERSION_ID;

final class BetterReflection
{
    /** @var int */
    public static $phpVersion = PHP_VERSION_ID;

    /** @var SourceLocator|null */
    private static $sharedSourceLocator;

    /** @var SourceLocator|null */
    private $sourceLocator;

    /** @var ClassReflector|null */
    private static $sharedClassReflector;

    /** @var ClassReflector|null */
    private $classReflector;

    /** @var FunctionReflector|null */
    private static $sharedFunctionReflector;

    /** @var FunctionReflector|null */
    private $functionReflector;

    /** @var ConstantReflector|null */
    private static $sharedConstantReflector;

    /** @var ConstantReflector|null */
    private $constantReflector;

    /** @var Parser|null */
    private static $sharedPhpParser;

    /** @var Parser|null */
    private $phpParser;

    /** @var SourceStubber|null */
    private static $sharedSourceStubber;

    /** @var SourceStubber|null */
    private $sourceStubber;

    /** @var AstLocator|null */
    private $astLocator;

    /** @var FindReflectionOnLine|null */
    private $findReflectionOnLine;

    public static function populate(
        SourceLocator $sourceLocator,
        ClassReflector $classReflector,
        FunctionReflector $functionReflector,
        ConstantReflector $constantReflector,
        Parser $phpParser,
        SourceStubber $sourceStubber
    ) : void {
        self::$sharedSourceLocator     = $sourceLocator;
        self::$sharedClassReflector    = $classReflector;
        self::$sharedFunctionReflector = $functionReflector;
        self::$sharedConstantReflector = $constantReflector;
        self::$sharedPhpParser         = $phpParser;
        self::$sharedSourceStubber     = $sourceStubber;
    }

    public function __construct()
    {
        $this->sourceLocator     = self::$sharedSourceLocator;
        $this->classReflector    = self::$sharedClassReflector;
        $this->functionReflector = self::$sharedFunctionReflector;
        $this->constantReflector = self::$sharedConstantReflector;
        $this->phpParser         = self::$sharedPhpParser;
        $this->sourceStubber     = self::$sharedSourceStubber;
    }

    public function sourceLocator() : SourceLocator
    {
        $astLocator    = $this->astLocator();
        $sourceStubber = $this->sourceStubber();

        return $this->sourceLocator
            ?? $this->sourceLocator = new MemoizingSourceLocator(new AggregateSourceLocator([
                new PhpInternalSourceLocator($astLocator, $sourceStubber),
                new EvaledCodeSourceLocator($astLocator, $sourceStubber),
                new AutoloadSourceLocator($astLocator, $this->phpParser()),
            ]));
    }

    public function classReflector() : ClassReflector
    {
        return $this->classReflector
            ?? $this->classReflector = new ClassReflector($this->sourceLocator());
    }

    public function functionReflector() : FunctionReflector
    {
        return $this->functionReflector
            ?? $this->functionReflector = new FunctionReflector($this->sourceLocator(), $this->classReflector());
    }

    public function constantReflector() : ConstantReflector
    {
        return $this->constantReflector
            ?? $this->constantReflector = new ConstantReflector($this->sourceLocator(), $this->classReflector());
    }

    public function phpParser() : Parser
    {
        return $this->phpParser
            ?? $this->phpParser = new MemoizingParser(
                (new ParserFactory())->create(ParserFactory::PREFER_PHP7, new Emulative([
                    'usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos'],
                ]))
            );
    }

    public function astLocator() : AstLocator
    {
        return $this->astLocator
            ?? $this->astLocator = new AstLocator($this->phpParser(), function () : FunctionReflector {
                return $this->functionReflector();
            });
    }

    public function findReflectionsOnLine() : FindReflectionOnLine
    {
        return $this->findReflectionOnLine
            ?? $this->findReflectionOnLine = new FindReflectionOnLine($this->sourceLocator(), $this->astLocator());
    }

    public function sourceStubber() : SourceStubber
    {
        return $this->sourceStubber
            ?? $this->sourceStubber = new AggregateSourceStubber(
                new PhpStormStubsSourceStubber($this->phpParser(), self::$phpVersion),
                new ReflectionSourceStubber()
            );
    }
}
