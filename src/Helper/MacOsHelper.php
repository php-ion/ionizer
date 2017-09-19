<?php

namespace Ionizer\Helper;


use Ionizer\HelperAbstract;

class MacOsHelper extends HelperAbstract
{

    public function getMemorySize(): int
    {
        return trim(`sysctl -n hw.memsize`);
    }

    public function buildFlags(): string
    {
        if ($this->filesExists(
            "/usr/local/opt/openssl/include",
            "/usr/local/opt/openssl/lib",
            "/usr/local/opt/openssl/lib/pkgconfig"
        )) {
            return 'CFLAGS="-I/usr/local/opt/openssl/include"'
                . ' LDFLAGS="-L/usr/local/opt/openssl/lib"'
                . ' PKG_CONFIG_PATH="/usr/local/opt/openssl/lib/pkgconfig"';
        } else {
            return "";
        }
    }
}