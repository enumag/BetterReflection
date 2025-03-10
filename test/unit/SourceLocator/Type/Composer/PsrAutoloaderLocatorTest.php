<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\Type\Composer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Identifier\Identifier;
use PHPStan\BetterReflection\Identifier\IdentifierType;
use PHPStan\BetterReflection\Reflection\Reflection;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Type\Composer\Psr\Psr4Mapping;
use PHPStan\BetterReflection\SourceLocator\Type\Composer\Psr\PsrAutoloaderMapping;
use PHPStan\BetterReflection\SourceLocator\Type\Composer\PsrAutoloaderLocator;
use Roave\BetterReflectionTest\Assets\DirectoryScannerAssets\Bar\FooBar;
use Roave\BetterReflectionTest\Assets\DirectoryScannerAssets\Foo;
use Roave\BetterReflectionTest\Assets\DirectoryScannerAssetsFoo\Bar\FooBar as FooBar1;
use Roave\BetterReflectionTest\Assets\DirectoryScannerAssetsFoo\Foo as Foo1;
use Roave\BetterReflectionTest\BetterReflectionSingleton;

use function array_map;
use function sort;

#[CoversClass(PsrAutoloaderLocator::class)]
class PsrAutoloaderLocatorTest extends TestCase
{
    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Type\Composer\Psr\PsrAutoloaderMapping&\PHPUnit\Framework\MockObject\MockObject
     */
    private $psrMapping;

    /**
     * @var \PHPStan\BetterReflection\Reflector\Reflector
     */
    private $reflector;

    /**
     * @var \PHPStan\BetterReflection\SourceLocator\Type\Composer\PsrAutoloaderLocator
     */
    private $psrLocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psrMapping = $this->createMock(PsrAutoloaderMapping::class);
        $this->psrLocator = new PsrAutoloaderLocator($this->psrMapping, BetterReflectionSingleton::instance()
            ->astLocator());
        $this->reflector  = new DefaultReflector($this->psrLocator);
        $this
            ->psrMapping
            ->method('directories')
            ->willReturn([
                __DIR__ . '/../../../Assets/DirectoryScannerAssets',
                __DIR__ . '/../../../Assets/DirectoryScannerAssetsFoo',
            ]);

        $this
            ->psrMapping
            ->method('resolvePossibleFilePaths')
            ->willReturnCallback(static function (Identifier $identifier): array {
                if ($identifier->getName() === Foo::class) {
                    return [__DIR__ . '/../../../Assets/DirectoryScannerAssets/Foo.php'];
                }

                if ($identifier->getName() === Foo::class . 'potato') {
                    return [__DIR__ . '/../../../Assets/DirectoryScannerAssets/Foopotato.php'];
                }

                if ($identifier->getName() === 'Roave\\BetterReflectionTest\\Assets\\DirectoryScannerAssets\\Bar\\Empty') {
                    return [__DIR__ . '/../../../Assets/DirectoryScannerAssets/Bar/Empty.php'];
                }

                return [];
            });
    }

    public function testWillLocateExistingFileWithMatchingClass(): void
    {
        $located = $this->psrLocator->locateIdentifier($this->reflector, new Identifier(Foo::class, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        self::assertNotNull($located);
        self::assertSame(Foo::class, $located->getName());
    }

    public function testWillNotLocateNonExistingFileWithMatchingPsr4Class(): void
    {
        self::assertNull($this->psrLocator->locateIdentifier($this->reflector, new Identifier(Foo::class . 'potato', new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testWillNotLocateExistingFileWithMatchingPsr4ClassAndNoContents(): void
    {
        self::assertNull($this->psrLocator->locateIdentifier($this->reflector, new Identifier('Roave\\BetterReflectionTest\\Assets\\DirectoryScannerAssets\\Bar\\Empty', new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testWillNotLocateClassNotMatchingPsr4Mappings(): void
    {
        self::assertNull($this->psrLocator->locateIdentifier($this->reflector, new Identifier('Blah', new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testWillNotLocateClassWhenFileDoesNotExist(): void
    {
        $astLocator = BetterReflectionSingleton::instance()
            ->astLocator();

        $locator = new PsrAutoloaderLocator(Psr4Mapping::fromArrayMappings([
            'Roave\\BetterReflectionTest\\Assets\\DirectoryScannerAssets\\' => [
                __DIR__ . '/../../../Assets/DirectoryScannerAssets',
            ],
        ]), $astLocator);

        self::assertNull($locator->locateIdentifier($this->reflector, new Identifier('BlahBlah', new IdentifierType(IdentifierType::IDENTIFIER_CLASS))));
    }

    public function testWillLocateAllClassesInMappedPsr4Paths(): void
    {
        $astLocator = BetterReflectionSingleton::instance()
            ->astLocator();

        $locator = new PsrAutoloaderLocator(Psr4Mapping::fromArrayMappings([
            'Roave\\BetterReflectionTest\\Assets\\DirectoryScannerAssets\\' => [
                __DIR__ . '/../../../Assets/DirectoryScannerAssets',
            ],
            'Roave\\BetterReflectionTest\\Assets\\DirectoryScannerAssetsFoo\\' => [
                __DIR__ . '/../../../Assets/DirectoryScannerAssetsFoo',
            ],
        ]), $astLocator);

        $expected = [
            FooBar::class,
            Foo::class,
            FooBar1::class,
            Foo1::class,
        ];

        $actual = array_map(static function (Reflection $reflection) : string {
            return $reflection->getName();
        }, $locator->locateIdentifiersByType(new DefaultReflector($locator), new IdentifierType(IdentifierType::IDENTIFIER_CLASS)));

        // Sorting may depend on filesystem here
        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual);
    }
}
