<?php

namespace Ionizer;


use Koda\ArgumentInfo;
use Koda\ClassInfo;
use Symfony\Component\Yaml\Yaml;

class Controller
{
    /**
     * @var Ionizer
     */
    public $ionizer;
    /**
     * @var Log
     */
    public $log;

    /**
     * @var ClassInfo
     */
    public $info;

    public function __construct(Ionizer $ionizer)
    {
        $this->ionizer = $ionizer;
        $this->log = $ionizer->log;
        $this->info = new ClassInfo(Controller::class);
        $this->info->scan([
            ClassInfo::METHODS => ClassInfo::FLAG_PUBLIC | ClassInfo::FLAG_NON_STATIC
        ], [
            ClassInfo::METHODS => "*Command"

        ]);
    }

    /**
     * This help
     * @param string $command Show help for this command
     */
    public function helpCommand(string $command = "")
    {
        $help = [];
        if ($command) {
            if (!$this->info->hasMethod($command."Command")) {
                throw new \LogicException("Command '$command' not found");
            }
            $method = $this->info->getMethod($command."Command");
            $args = [];
            foreach ($method->args as $name => $arg) {
                /** @var ArgumentInfo $arg */
                if ($arg->optional) {
                    $args[] = "[$name]";
                } else {
                    $args[] = "$name";
                }
            }
            $help[] = "Usage: ion $command " . implode(" ", $args);
            $help[] = "About: ".str_replace("\n", "\n       ", $method->getDescription());
            $help[] = "";
            if ($method->args) {
                $help[] = "Arguments:";
                foreach ($method->args as $name => $arg) {
                    if ($arg->optional) {
                        $optional = "By default is ".var_export($arg->default, true).".";
                    } else {
                        $optional = "";
                    }
                    $desc = rtrim($arg->getDescription(), "."). ". ".$optional;
                    // add padding
                    $desc = str_replace("\n", "\n" . sprintf("  %-10s // ", ""), $desc);
                    $help[] = sprintf("  %-10s // %s", $name, $desc);
                }
            } else {
                $help[] = "No arguments";
            }
        } else {
            $help[] = "Usage: ion [options] command [arguments]";
            $help[] = "Help:  ion help";
            $help[] = "       ion help [command]";
            $help[] = "";
            $help[] = "Commands:";
            foreach ($this->info->methods as $name => $info) {
                $name = str_replace("Command", "", $name);
                $help[] = sprintf("  %-10s // %s", $name, $info->getDescription());
            }
        }

        $help[] = "";
        $help[] = "Options:";
        $help[] = sprintf("  %-10s %s", "--debug", "Enable ionizer debug mode");
        $help[] = sprintf("  %-10s %s", "--expand", "Expand environment variables like \$IONIZER_FLAGS");
        echo implode("\n", $help)."\n";
    }

    /**
     * Show summary info
     */
    public function infoCommand()
    {
        $info = [];
        $bin_path = $this->ionizer->getExtPath();

        $info["ION"] = [
            "version" => $this->getCurrentVersion(),
            "binary" => realpath($bin_path) ?: $bin_path,
            "link"   => $this->ionizer->link
        ];
        $info["PHP"] = [
            "version" => PHP_VERSION,
            "debug" => (bool) PHP_DEBUG || ZEND_DEBUG_BUILD,
            "zts" => (bool) PHP_ZTS,
            "cmd" => $this->ionizer->getPhpCmd(),
            "IONIZER_FLAGS" => getenv('IONIZER_FLAGS')
        ];
        $info["OS"] = [
            "family" => defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS,
            "uname" => php_uname('a'),
        ];

        foreach ($info as $name => $section) {
            echo "$name:\n";
            foreach ($section as $key => $value) {
                if (is_bool($value)) {
                    echo "    $key: ".($value ? "true" : "false")."\n";
                } else {
                    echo "    $key: $value\n";
                }
            }
        }
    }

    public function getCurrentVersion(): string
    {
        return basename(dirname(readlink($this->ionizer->link)));
    }

    /**
     * Show available version
     *
     * @param bool $all (is a) Show all available versions, including versions in the repository
     */
    public function versionsCommand(bool $all = false)
    {
        $index = $this->ionizer->getIndex($all ? true : false);
        $current = $this->getCurrentVersion();
        foreach ($index["variants"] as $version => $variant) {
            if ($current == $version) {
                $marker = "*";
            } elseif (file_exists($this->ionizer->cache_dir . "/$version/".$this->ionizer->link_filename)) {
                $marker = "v";
            } else {
                $marker = " ";
            }
            echo " $marker $version\n";
        }
    }

    /**
     * Switch ion version
     * @param string $version
     */
    public function versionCommand(string $version)
    {
        $this->ionizer->selectVersion($version);
    }

    /**
     * Update version's index
     */
    public function updateCommand()
    {

    }

    /**
     *  Update version's index and upgrade ion to newest version
     */
    public function upgradeCommand()
    {

    }

    /**
     * Remove all unused ion versions
     */
    public function pruneCommand()
    {

    }

    /**
     * Run PHP code
     * @param string $code PHP code without using script tags <?..?>
     */
    public function runCommand(string $code)
    {
        $cmd = $this->ionizer->getPhpCmd() . " -r " . escapeshellarg($code . ';');
        $this->log->debug($cmd);
        passthru($cmd, $status);
    }

    /**
     * Parse and execute PHP file
     * @param string $path
     */
    public function scriptCommand(string $path)
    {
        $cmd = $this->ionizer->getPhpCmd() . " $path";
        $this->log->debug($cmd);
        passthru($cmd, $status);
        exit($status);
    }

    /**
     * Test current ion extension
     */
    public function testCommand() {

    }

    /**
     * Start async server
     * @param string $listen IP and port, like 127.0.0.1:8088
     * @param string $router PHP script or class
     */
    public function serverCommand(string $listen, string $router)
    {

    }

}