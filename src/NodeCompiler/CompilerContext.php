<?php

declare(strict_types=1);

namespace Roave\BetterReflection\NodeCompiler;

use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionClassConstant;
use Roave\BetterReflection\Reflection\ReflectionConstant;
use Roave\BetterReflection\Reflection\ReflectionEnumCase;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionParameter;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\Util\FileHelper;

/**
 * @internal
 */
class CompilerContext
{
    /**
     * @var \Roave\BetterReflection\Reflector\Reflector
     */
    private $reflector;
    /**
     * @var \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionClassConstant|\Roave\BetterReflection\Reflection\ReflectionConstant|\Roave\BetterReflection\Reflection\ReflectionEnumCase|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty
     */
    private $contextReflection;
    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionClass|\Roave\BetterReflection\Reflection\ReflectionClassConstant|\Roave\BetterReflection\Reflection\ReflectionConstant|\Roave\BetterReflection\Reflection\ReflectionEnumCase|\Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|\Roave\BetterReflection\Reflection\ReflectionParameter|\Roave\BetterReflection\Reflection\ReflectionProperty $contextReflection
     */
    public function __construct(Reflector $reflector, $contextReflection)
    {
        $this->reflector = $reflector;
        $this->contextReflection = $contextReflection;
    }
    public function getReflector(): Reflector
    {
        return $this->reflector;
    }

    public function getFileName(): ?string
    {
        if ($this->contextReflection instanceof ReflectionConstant) {
            $fileName = $this->contextReflection->getFileName();
            if ($fileName === null) {
                return null;
            }

            return $this->realPath($fileName);
        }

        $fileName = (($getClass = $this->getClass()) ? $getClass->getFileName() : null) ?? (($getFunction = $this->getFunction()) ? $getFunction->getFileName() : null);
        if ($fileName === null) {
            return null;
        }

        return $this->realPath($fileName);
    }

    private function realPath(string $fileName): string
    {
        return FileHelper::normalizePath($fileName, '/');
    }

    public function getNamespace(): string
    {
        if ($this->contextReflection instanceof ReflectionConstant) {
            return $this->contextReflection->getNamespaceName();
        }

        return (($getClass = $this->getClass()) ? $getClass->getNamespaceName() : null) ?? (($getFunction = $this->getFunction()) ? $getFunction->getNamespaceName() : null) ?? '';
    }

    public function getClass(): ?ReflectionClass
    {
        if ($this->contextReflection instanceof ReflectionClass) {
            return $this->contextReflection;
        }

        if ($this->contextReflection instanceof ReflectionFunction) {
            return null;
        }

        if ($this->contextReflection instanceof ReflectionConstant) {
            return null;
        }

        if ($this->contextReflection instanceof ReflectionClassConstant) {
            return $this->contextReflection->getDeclaringClass();
        }

        if ($this->contextReflection instanceof ReflectionEnumCase) {
            return $this->contextReflection->getDeclaringClass();
        }

        return $this->contextReflection->getImplementingClass();
    }

    /**
     * @return \Roave\BetterReflection\Reflection\ReflectionFunction|\Roave\BetterReflection\Reflection\ReflectionMethod|null
     */
    public function getFunction()
    {
        if ($this->contextReflection instanceof ReflectionMethod) {
            return $this->contextReflection;
        }

        if ($this->contextReflection instanceof ReflectionFunction) {
            return $this->contextReflection;
        }

        if ($this->contextReflection instanceof ReflectionParameter) {
            return $this->contextReflection->getDeclaringFunction();
        }

        return null;
    }
}
