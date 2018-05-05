<?php

namespace Ionizer\Helper;


use Ionizer\Ionizer;

class BaseHelper
{
    const FAMILY = "unknown";

    const BUILD_DEBUG = 1;
    const BUILD_COVERAGE = 2;
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

    public function getOsName(): array
    {
        return ["undefined", "0.0", "undefined"];
    }

    public function buildFlags(int $flags = 0): string
    {
        return "";
    }


    public function getCPUCount(): int
    {
        return 1;
    }

    public function filesExists(...$paths)
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                return false;
            }
        }
        return true;
    }

    public function getCoreDumpPath(): string
    {
        return "";
    }

}