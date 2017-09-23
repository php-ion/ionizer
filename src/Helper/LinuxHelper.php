<?php

namespace Ionizer\Helper;


use Ionizer\HelperAbstract;

class LinuxHelper extends HelperAbstract
{

    const FAMILY = "linux";

    public function getMemorySize(): int
    {
        return trim(`sysctl -n hw.memsize`);
    }

    public function buildFlags(): string
    {
        return "";
    }

    public function getOsName(): array
    {
        // by default for all linuxes uses build for ubuntu 16.04
        $os_name = "ubuntu";
        $os_release = "16.04";
        if (file_exists('/etc/lsb-release')) {

            // Example:
            //  DISTRIB_ID=Ubuntu
            //  DISTRIB_RELEASE=16.04
            //  DISTRIB_CODENAME=xenial
            //  DISTRIB_DESCRIPTION="Ubuntu 16.04.3 LTS"
            $lsb_data = trim(file_get_contents('/etc/lsb-release'));
            $this->ionizer->log->debug($lsb_data);
            foreach (explode("\n", $lsb_data) as $line) {
                list($key, $value) = explode("=", $line, 2);
                if ($key == "DISTRIB_ID") {
                    $os_name = strtolower($value);
                } elseif ($key == "DISTRIB_RELEASE") {
                    $os_release = $value;
                }
            }
        }

        return [$os_name, $os_release, self::FAMILY];
    }
}