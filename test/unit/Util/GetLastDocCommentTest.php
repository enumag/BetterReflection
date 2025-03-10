<?php

declare(strict_types=1);

namespace Roave\BetterReflectionTest\Util;

use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPStan\BetterReflection\Util\GetLastDocComment;

#[CoversClass(GetLastDocComment::class)]
class GetLastDocCommentTest extends TestCase
{
    public function testWithComment(): void
    {
        $comment = new Comment('/* An ordinary comment */');
        $node    = new Function_('test', [], ['comments' => [$comment]]);

        self::assertNull(GetLastDocComment::forNode($node));
    }

    public function testWithoutComment(): void
    {
        $node = new Function_('test');

        self::assertNull(GetLastDocComment::forNode($node));
    }

    public function testWithMixedCommentTypes(): void
    {
        $comment    = new Comment('/* An ordinary comment */');
        $docComment = new Doc('/** Property description */');
        $node       = new Function_('test', [], ['comments' => [$comment, $docComment]]);

        self::assertSame('/** Property description */', GetLastDocComment::forNode($node));
    }

    public function testWithMultipleDocComments(): void
    {
        $comment1 = new Doc('/** First doc comment */');
        $comment2 = new Doc('/** Second doc comment */');
        $node     = new Function_('test', [], ['comments' => [$comment1, $comment2]]);

        self::assertSame('/** Second doc comment */', GetLastDocComment::forNode($node));
    }
}
