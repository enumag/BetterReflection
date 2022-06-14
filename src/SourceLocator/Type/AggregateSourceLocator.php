<?php

declare(strict_types=1);

namespace Roave\BetterReflection\SourceLocator\Type;

use Roave\BetterReflection\Identifier\Identifier;
use Roave\BetterReflection\Identifier\IdentifierType;
use Roave\BetterReflection\Reflection\Reflection;
use Roave\BetterReflection\Reflector\Reflector;

use function array_map;
use function array_merge;

class AggregateSourceLocator implements SourceLocator
{
    /**
     * @var list<SourceLocator>
     */
    private $sourceLocators = [];
    /**
     * @param list<SourceLocator> $sourceLocators
     */
    public function __construct(array $sourceLocators = [])
    {
        $this->sourceLocators = $sourceLocators;
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        foreach ($this->sourceLocators as $sourceLocator) {
            $located = $sourceLocator->locateIdentifier($reflector, $identifier);

            if ($located) {
                return $located;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return array_merge([], ...array_map(static function (SourceLocator $sourceLocator) use ($reflector, $identifierType) : array {
            return $sourceLocator->locateIdentifiersByType($reflector, $identifierType);
        }, $this->sourceLocators));
    }
}
