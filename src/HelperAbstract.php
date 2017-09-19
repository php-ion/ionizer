<?php

namespace Ionizer;


class HelperAbstract
{
    public $ionizer;
    public function __construct(Ionizer $ionizer)
    {
        $this->ionizer = $ionizer;
    }

    abstract public function buildFlags(): string;
    abstract public function getMemorySize(): int;

    public function filesExists(...$paths)
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                return false;
            }
        }
        return true;
    }
}