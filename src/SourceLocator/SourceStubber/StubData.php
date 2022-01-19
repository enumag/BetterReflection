<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\SourceStubber;

/** @internal */
class StubData
{
    public function __construct(private string $stub, private string|null $extensionName, private ?string $fileName)
    {
    }

    public function getStub(): string
    {
        return $this->stub;
    }

    public function getExtensionName(): string|null
    {
        return $this->extensionName;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }
}
