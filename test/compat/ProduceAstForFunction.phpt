--TEST--
Produce the AST for a specific function
--FILE--
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

$source = <<<'EOF'
<?php

function adder($a, $b)
{
    return $a + $b;
}
EOF;

$sourceLocator = new PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator(
    $source,
    (new PHPStan\BetterReflection\BetterReflection())->astLocator()
);

$reflector = new \PHPStan\BetterReflection\Reflector\DefaultReflector($sourceLocator);

$functionInfo = $reflector->reflectFunction('adder');
var_dump($functionInfo->getAst() instanceof PhpParser\Node\Stmt\Function_);

?>
--EXPECTF--
bool(true)
