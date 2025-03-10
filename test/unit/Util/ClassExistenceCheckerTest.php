<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Util;

use Closure;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Util\ClassExistenceChecker;
use Roave\BetterReflectionTest\Fixture\ExampleClass;
use Roave\BetterReflectionTest\Fixture\ExampleInterface;
use Roave\BetterReflectionTest\Fixture\ExampleTrait;
use stdClass;
use TraitFixtureTraitA;

use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(ClassExistenceChecker::class)]
class ClassExistenceCheckerTest extends TestCase
{
    /**
     * @var \Closure|null
     */
    private $loader = null;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../Fixture/TraitFixture.php';

        $this->loader = static function (): void {
            // Should not be called
            self::fail();
        };
        spl_autoload_register($this->loader);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        spl_autoload_unregister($this->loader);
    }

    /** @return list<array{0: string, 1: bool}> */
    public static function dataClassExists(): array
    {
        return [
            [stdClass::class, true],
            [IteratorAggregate::class, false],
            [ExampleClass::class, false],
            ['NotExistingClass', false],
        ];
    }

    #[DataProvider('dataClassExists')]
    public function testClassExists(string $name, bool $exists): void
    {
        self::assertSame($exists, ClassExistenceChecker::classExists($name, false));
    }

    /** @return list<array{0: string, 1: bool}> */
    public static function dataExists(): array
    {
        return [
            [stdClass::class, true],
            [IteratorAggregate::class, true],
            [TraitFixtureTraitA::class, true],
            [ExampleClass::class, false],
            ['NotExistingClass', false],
            [ExampleInterface::class, false],
            ['NotExistInterface', false],
            [ExampleTrait::class, false],
            ['NotExistTrait', false],
        ];
    }

    #[DataProvider('dataExists')]
    public function testExists(string $name, bool $exists): void
    {
        self::assertSame($exists, ClassExistenceChecker::exists($name, false));
    }

    /** @return list<array{0: string, 1: bool}> */
    public static function dataInterfaceExists(): array
    {
        return [
            [IteratorAggregate::class, true],
            [stdClass::class, false],
            [ExampleInterface::class, false],
            ['NotExistInterface', false],
        ];
    }

    #[DataProvider('dataInterfaceExists')]
    public function testInterfaceExists(string $name, bool $exists): void
    {
        self::assertSame($exists, ClassExistenceChecker::interfaceExists($name, false));
    }

    /** @return list<array{0: string, 1: bool}> */
    public static function dataTraitExists(): array
    {
        return [
            [stdClass::class, false],
            [TraitFixtureTraitA::class, true],
            [ExampleTrait::class, false],
            ['NotExistTrait', false],
        ];
    }

    #[DataProvider('dataTraitExists')]
    public function testTraitExists(string $name, bool $exists): void
    {
        self::assertSame($exists, ClassExistenceChecker::traitExists($name, false));
    }
}
