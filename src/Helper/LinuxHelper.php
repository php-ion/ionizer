<?php

namespace Ionizer\Helper;



class LinuxHelper extends BaseHelper
{
    const FAMILY = "linux";

    public static $releases = [
        '/etc/lsb-release' => [ //  ubuntu & co
            "id" => "DISTRIB_ID",
            "version" => "DISTRIB_RELEASE"
        ],
        '/etc/os-release'  => [ // debian & co
            "id" => "ID",
            "version" => "VERSION_ID",
        ],
    ];

    public function getMemorySize(): int
    {
        return -1;
    }

    public function buildFlags(int $flags = 0): string
    {
        $cflags  = ["-std=gnu99"];
        $ldflags = [];
        $env     = [];

        if ($flags & self::BUILD_DEBUG) {
            $cflags[] = "-Wall -g3 -ggdb -O0";
        }
        if ($flags & self::BUILD_COVERAGE) {
            $cflags[]  = "-fprofile-arcs -ftest-coverage";
            $ldflags[] = "-fprofile-arcs -ftest-coverage";
        }

        return 'CFLAGS="$CFLAGS ' . implode(" ", $cflags) . '" '.
            'LDFLAGS="$LDFLAGS ' . implode(" ", $ldflags) . '" '.
            implode(" ", $env);
    }

    public function getOsName(): array
    {
        // by default for all linuxes uses build for ubuntu 16.04
        $os_name = "ubuntu";
        $os_release = "16.04";
        foreach (self::$releases as $path => $fields) {
            if (file_exists($path)) {
                $lsb_data = trim(file_get_contents($path));
                foreach (explode("\n", $lsb_data) as $line) {
                    list($key, $value) = explode("=", $line, 2);
                    if ($key == $fields["id"]) {
                        $os_name = strtolower($value);
                    } elseif ($key == $fields["version"]) {
                        $os_release = $value;
                    }
                }
            }
        }
        return [$os_name, $os_release, self::FAMILY];
    }

    public function getReleaseNotes() {

    }

    public function getCPUCount(): int
    {
        return intval(trim(`nproc`));
    }

    public function getCoreDumpPath(): string
    {
        return file_get_contents("/proc/sys/kernel/core_pattern");
    }

    public function getCFlags(): string
    {
        return "";
    }

    public function getLDFlags(): string
    {
        return "";
    }
}