<?php

namespace Ionizer;


use Ionizer\Helper\LinuxHelper;
use Ionizer\Helper\MacOsHelper;

abstract class HelperAbstract
{
    const FAMILY = "unknown";
    public $ionizer;

    public static function getInstance(Ionizer $ionizer) : self
    {
        $os = strtolower(PHP_OS);
        if ($os == "linux") {
            return new LinuxHelper($ionizer);
        } elseif ($os == "darwin") {
            return new MacOsHelper($ionizer);
        } else {
            throw new \RuntimeException("Your OS ($os) unsupported");
        }

    }

    public function __construct(Ionizer $ionizer)
    {
        $this->ionizer = $ionizer;
    }

    abstract public function getOsName() : array ;

    abstract public function buildFlags(): string;
//    abstract public function getMemorySize(): int;

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