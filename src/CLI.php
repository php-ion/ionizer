<?php
/**
 *
 */

namespace Ionizer;


class CLI
{
    const TRANSFORM = [
        "yes" => true,
        "no"  => false,
        "true" => true,
        "false" => false,
    ];

    const COLORS = [
        "red"     => "\e[0;31m",
        "green"   => "\e[0;32m",
        "yellow"  => "\e[0;33m",
        "blue"    => "\e[0;34m",
        "magenta" => "\e[0;35m",
        "cyan"    => "\e[0;36m",
        "white"   => "\e[0;37m",

        "bold"     => "\e[1m",

        "red:bold"     => "\e[1;31m",
        "green:bold"   => "\e[1;32m",
        "yellow:bold"  => "\e[1;33m",
        "blue:bold"    => "\e[1;34m",
        "magenta:bold" => "\e[1;35m",
        "cyan:bold"    => "\e[1;36m",
        "white:bold"   => "\e[1;37m",

        "black:bg"   => "\e[40m",
        "red:bg"     => "\e[41m",
        "green:bg"   => "\e[42m",
        "yellow:bg"  => "\e[43m",
        "blue:bg"    => "\e[44m",
        "magenta:bg" => "\e[45m",
        "cyan:bg"    => "\e[46m",
        "white:bg"   => "\e[47m",
    ];

    const RESET = "\e[0m";

    public static function parse(string $str): string
    {
        return preg_replace_callback('#\</?cli(.*?)\>#s', function ($matches) {
            if ($matches[1]) {
                $pattern = trim($matches[1], ":");
                if (isset(self::COLORS[$pattern])) {
                    return self::COLORS[$pattern];
                }
            } elseif ($matches[0][1] == "/") {
                return self::RESET;
            }
            return "";

        }, $str);
    }

    public static function sprintf(string $str, ...$params): string
    {
        return sprintf(self::parse($str), ...$params);
    }

    /**
     * Pop CLI parameters begins with --
     * @param array $cli_args
     * @return array
     */
    public static function popOptions(array &$cli_args): array
    {
        $options = [];
        while ($option = current($cli_args)) {
            if ($option[0] == "-" && $option[1] == "-") {
                $option = ltrim($option, "-");
                if (!$option) {
                    break;
                }
                array_shift($cli_args);
                if (strpos($option, "=")) {
                    [$key, $value] = explode("=", $option, 2);
                    if (isset(self::TRANSFORM[strtolower($value)])) {
                        $options[$key] = self::TRANSFORM[strtolower($value)];
                    } else {
                        $options[$key] = $value;
                    }
                } else {
                    $options[$option] = true;
                }
            } else {
                break;
            }
        }

        return $options;
    }

    public static function popArguments(array &$cli_args, int $count = -1): array
    {
        $args = [];
        while ($arg = current($cli_args)) {
            if ($arg[0] == "-" || strpos($arg, "=") ) {
                break;
            }
            $args[] = $arg;
            array_shift($cli_args);
            if ($count >= 0 && --$count == 0) {
                break;
            }
        }

        return $args;
    }

    public static function popArgument(array &$cli_args): ?string
    {
        $args = self::popArguments($cli_args, 1);
        if ($args) {
            return $args[0];
        } else {
            return null;
        }
    }


    /**
     * Scan argv
     * @param array $cli_args
     * @param bool $with_command
     * @return array
     */
    public static function scanArgv(array $cli_args, $with_command = true) : array
    {
        $options = [];
        $command = "";
        $args    = [];
        foreach($cli_args as $arg) {
            $key = "";
            $value = null;
            if ($arg[0] == "-") {
                if (strlen($arg) > 1) {
                    if($arg[1] == "-") { // long option
                        $arg = ltrim($arg, "-");
                        list($key, $value) = explode("=", $arg, 2) + ["", true];
                    } else { // short option
                        $key = trim($arg, "-");
                        $value = true;
                    }
                } else {
                    $value = "-";
                }
            } else {
                if($with_command) {
                    if ($command) {
                        $value = $arg;
                    } else {
                        $command = $arg;
                    }
                } else {
                    continue;
                }
            }
            if ($value === null || strtolower($value) == "no") {
                continue;
            }
            if ($command) {
                if ($key) {
                    $args[$key] = $value;
                } else {
                    $args[] = $value;
                }
            } else {
                if ($key) {
                    $options[$key] = $value;
                } else {
                    $options[] = $value;
                }
            }
        }

        return [$options, $command, $args];
    }

}