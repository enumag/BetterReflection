<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\SourceLocator\Located;

/**
 * @internal
 */
class EvaledLocatedSource extends LocatedSource
{
    public function isEvaled(): bool
    {
        return true;
    }
}
