<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\Located;

/** @internal */
class AliasLocatedSource extends LocatedSource
{
    /**
     * @var string
     */
    private $aliasName;
    public function __construct(string $source, string $name, ?string $filename, string $aliasName)
    {
        $this->aliasName = $aliasName;
        parent::__construct($source, $name, $filename);
    }

    public function getAliasName(): ?string
    {
        return $this->aliasName;
    }
}
