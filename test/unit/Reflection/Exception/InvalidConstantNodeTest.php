<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection\Exception;

use PhpParser\Node;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Reflection\Exception\InvalidConstantNode;

/**
 * @covers \PHPStan\BetterReflection\Reflection\Exception\InvalidConstantNode
 */
class InvalidConstantNodeTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = InvalidConstantNode::create(new Node\UnionType([new Node\Name('Whatever\Something\Anything'), new Node\Name('\Very\Long\Name\That\Will\Be\Truncated')]));

        self::assertInstanceOf(InvalidConstantNode::class, $exception);
        self::assertSame('Invalid constant node (first 50 characters: Whatever\Something\Anything|\Very\Long\Name\That\W)', $exception->getMessage());
    }
}
