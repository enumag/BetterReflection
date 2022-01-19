<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\Located;

/**
 * @internal
 */
class InternalLocatedSource extends LocatedSource
{
    public function __construct(string $source, string $name, private string $extensionName, ?string $fileName = null)
    {
        parent::__construct($source, $name, $fileName);
    }

    public function isInternal(): bool
    {
        return true;
    }

    public function getExtensionName(): ?string
    {
        return $this->extensionName;
    }
}
