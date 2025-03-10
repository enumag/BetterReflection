<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\StringCast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\ReflectionIntersectionType;
use PHPStan\BetterReflection\Reflection\ReflectionNamedType;
use PHPStan\BetterReflection\Reflection\ReflectionUnionType;
use PHPStan\BetterReflection\Reflection\StringCast\ReflectionTypeStringCast;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use Roave\BetterReflectionTest\BetterReflectionSingleton;

use function assert;

#[CoversClass(ReflectionTypeStringCast::class)]
final class ReflectionTypeStringCastTest extends TestCase
{
    /** @return list<array{0: ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType, 1: string}> */
    public static function toStringProvider(): array
    {
        $reflector = new DefaultReflector(new StringSourceLocator(<<<'PHP'
<?php

interface A {}
interface B {}
function a(): int|string|null {}
function b(): int|null {}
function c(): ?int {}
function d(): A&B {}
function e(): int {}
function f(): null|int {}
function g(): string|null|int {}
function h(): null {}
function i(): (A&B)|null {}
function j(): (A&B)|(A&C) {}
function k(): null|(A&B)|(A&C) {}
PHP, BetterReflectionSingleton::instance()
            ->astLocator()));

        $returnTypeForFunction = static function (string $function) use ($reflector) {
            $type = $reflector->reflectFunction($function)
                ->getReturnType();

            assert($type !== null);

            return $type;
        };

        return [
            [$returnTypeForFunction('a'), 'int|string|null'],
            [$returnTypeForFunction('b'), '?int'],
            [$returnTypeForFunction('c'), '?int'],
            [$returnTypeForFunction('d'), 'A&B'],
            [$returnTypeForFunction('e'), 'int'],
            [$returnTypeForFunction('f'), '?int'],
            [$returnTypeForFunction('g'), 'string|null|int'],
            [$returnTypeForFunction('h'), 'null'],
            [$returnTypeForFunction('i'), '(A&B)|null'],
            [$returnTypeForFunction('j'), '(A&B)|(A&C)'],
            [$returnTypeForFunction('k'), 'null|(A&B)|(A&C)'],
        ];
    }

    /**
     * @param \PHPStan\BetterReflection\Reflection\ReflectionNamedType|\PHPStan\BetterReflection\Reflection\ReflectionUnionType|\PHPStan\BetterReflection\Reflection\ReflectionIntersectionType $type
     */
    #[DataProvider('toStringProvider')]
    public function testToString($type, string $expectedString) : void
    {
        self::assertSame($expectedString, ReflectionTypeStringCast::toString($type));
    }
}
