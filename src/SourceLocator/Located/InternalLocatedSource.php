<?php

declare(strict_types=1);

namespace PHPStan\BetterReflection\SourceLocator\Located;

class InternalLocatedSource extends LocatedSource
{
    /** @var string */
    private $extensionName;

    /**
     * {@inheritDoc}
     */
    public function __construct(string $source, string $extensionName, ?string $fileName = null)
    {
        parent::__construct($source, $fileName);

        $this->extensionName = $extensionName;
    }

    public function isInternal() : bool
    {
        return true;
    }

    public function getExtensionName() : ?string
    {
        return $this->extensionName;
    }
}
