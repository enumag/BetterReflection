<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\SourceLocator\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\SourceLocator\Exception\InvalidFileInfo;
use stdClass;

#[CoversClass(InvalidFileInfo::class)]
class InvalidFileInfoTest extends TestCase
{
    /**
     * @param mixed $value
     */
    #[DataProvider('nonSplFileInfoProvider')]
    public function testFromNonSplFileInfo(string $expectedMessage, $value): void
    {
        $exception = InvalidFileInfo::fromNonSplFileInfo($value);

        self::assertInstanceOf(InvalidFileInfo::class, $exception);
        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function nonSplFileInfoProvider(): array
    {
        return [
            ['Expected an iterator of SplFileInfo instances, stdClass given instead', new stdClass()],
            ['Expected an iterator of SplFileInfo instances, boolean given instead', true],
            ['Expected an iterator of SplFileInfo instances, NULL given instead', null],
            ['Expected an iterator of SplFileInfo instances, integer given instead', 100],
            ['Expected an iterator of SplFileInfo instances, double given instead', 100.35],
            ['Expected an iterator of SplFileInfo instances, array given instead', []],
        ];
    }
}
