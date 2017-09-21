<?php

namespace Ionizer\Helper;


use Ionizer\HelperAbstract;

class LinuxHelper extends HelperAbstract
{

    public function getMemorySize(): int
    {
        return trim(`sysctl -n hw.memsize`);
    }

    public function buildFlags(): string
    {
        return "";
    }
}