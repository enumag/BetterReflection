<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\SourceLocator\Located;

/**
 * @internal
 */
class InternalLocatedSource extends LocatedSource
{
    /**
     * @var string
     */
    private $extensionName;
    public function __construct(string $source, string $name, string $extensionName, ?string $fileName = null)
    {
        $this->extensionName = $extensionName;
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
