<?php # inspired by https://github.com/Roave/BetterReflection/issues/276

# parse all classes in a directory that use some dependencies from /vendor

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPStan\BetterReflection\BetterReflection;
use PHPStan\BetterReflection\Reflector\ClassReflector;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;

$directories = [__DIR__ . '/../../src'];

$sourceLocator = new AggregateSourceLocator([
    new DirectoriesSourceLocator(
        $directories,
        (new BetterReflection())->astLocator()
    ),
    // ↓ required to autoload parent classes/interface from another directory than /src (e.g. /vendor)
    new AutoloadSourceLocator((new BetterReflection())->astLocator())
]);

$classReflector = new ClassReflector($sourceLocator);

$classReflections = $classReflector->getAllClasses();

!empty($classReflections) && print 'success';
