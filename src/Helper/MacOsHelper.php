<?php

namespace Ionizer\Helper;


class MacOsHelper extends BaseHelper
{

    const FAMILY = "macos";

    const REPOS = [
        "macports" => [
            "ssl_include" => "/opt/local/include",
            "ssl_lib" => "/opt/local/lib",
            "pkgconfig" => "/opt/local/lib/pkgconfig",
        ],
        "brew" => [
            "ssl_include" => "/usr/local/opt/openssl/include",
            "ssl_lib" => "-L/usr/local/opt/openssl/lib",
            "pkgconfig" => "/usr/local/opt/openssl/lib/pkgconfig",
        ]
    ];

    public function getMemorySize(): int
    {
        return trim(`sysctl -n hw.memsize`);
    }

    public function buildFlags(int $flags = 0): string
    {
        $cflags  = ["-std=gnu99"];
        $ldflags = [];
        $flags   = [];

        foreach (self::REPOS as $repo) {
            if ($this->filesExists(...array_values($repo))) {
                $cflags[] = "-I" . $repo["ssl_include"];
                $ldflags[] = "-L" . $repo["ssl_lib"];
                $flags[] = 'PKG_CONFIG_PATH='.$repo["pkgconfig"].'';
            }
        }
        if(fnmatch('1*.*.*', php_uname("r"))) {
            $cflags[] = "-arch x86_64 -mmacosx-version-min=10.5";
        } else {
            $cflags[] = "-arch x86_64 -arch ppc -arch ppc64";
        }
        if ($flags & self::BUILD_DEBUG) {
            $cflags[] = "-Wall -g3 -ggdb -O0";
        }
        if ($flags & self::BUILD_COVERAGE) {
            $cflags[]  = "-fprofile-arcs -ftest-coverage";
            $ldflags[] = "-fprofile-arcs -ftest-coverage";
        }

        return 'CFLAGS="$CFLAGS ' . implode(" ", $cflags) . '" '.
            'LDFLAGS="$LDFLAGS ' . implode(" ", $ldflags) . '" '.
            implode(" ", $flags);
    }

    public function getOsName(): array
    {
        return ["darwin", php_uname("r"), self::FAMILY];
    }

    public function getCPUCount(): int
    {
        return intval(`sysctl -n hw.ncpu`);
    }

    public function getCoreDumpPath(): string
    {
        return "/cores";
    }
}