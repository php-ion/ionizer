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
                    $args[] = "[<$name>]";
                } else {
                    $args[] = "<$name>";
                }
            }
            $help[] = "Usage:";
            $help[] = "  ion $command " . implode(" ", $args);
            $help[] = "";
            if ($method->args) {
                $help[] = "Arguments:";
                foreach ($method->args as $name => $arg) {
                    $desc = rtrim($arg->getDescription(), "."). ".";
                    // add padding
                    $desc = str_replace("\n", "\n" . sprintf("  %-10s // ", ""), $desc);
                    $help[] = sprintf("  %-10s // %s", $name, $desc);
                }
            } else {
                $help[] = "No arguments";
            }
            $help[] = "";
            $help[] = "About:";
            $help[] = "  ".str_replace("\n", "\n  ", $method->getDescription());
        } else {
            $help[] = "Usage:";
            $help[] = "  ion [options] command [arguments]";
            $help[] = "";
            $help[] = "Help:";
            $help[] = "  ion help";
            $help[] = "  ion help <command>";
            $help[] = "";
            $help[] = "Commands:";
            foreach ($this->info->methods as $name => $info) {
                $name = str_replace("Command", "", $name);
                if (strpos($info->getDescription(), "\n")) {
                    $desc = strstr($info->getDescription(), "\n", true);
                } elseif (strpos($info->getDescription(), ". ")) {
                    $desc = strstr($info->getDescription(), ".", true);
                } else {
                    $desc = $info->getDescription();
                }
                $help[] = sprintf("  %-10s // %s", $name, $desc);
            }
        }

        $help[] = "";
//        $help[] = "Options:";
//        $help[] = sprintf("  %-10s %s", "--debug", "Enable ionizer debug mode");
//        $help[] = sprintf("  %-10s %s", "--expand", "Expand environment variables like \$IONIZER_FLAGS");
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
     * The command download new index of versions from remote repository.
     */
    public function updateCommand()
    {
        $this->ionizer->getIndex(true);
    }

    /**
     * Update index and upgrade ion to newest version
     * The command download new index of versions from remote repository.
     * After that reads the index of versions and updates the php-ion to newest version
     */
    public function upgradeCommand()
    {
        $this->ionizer->getIndex(true);
        $this->ionizer->selectVersion();
    }

    /**
     * Remove all unused ion versions
     */
    public function pruneCommand()
    {

    }

    /**
     * Evaluate a string as PHP code.
     * The code must not be wrapped in opening and closing PHP tags.
     *
     * It is still possible to leave and re-enter PHP mode though using the appropriate PHP tags.
     *
     * Apart from that the passed code must be valid PHP.
     * This includes that all statements must be properly terminated using a semicolon.
     *
     * A return statement will immediately terminate the evaluation of the code.
     *
     * @param string $code Valid PHP code to be evaluated.
     * @param array $args Any command line arguments for code
     */
    public function evalCommand(string $code, ...$args)
    {
        if ($args) {
            if ($args[0] !== "--") {
                array_unshift($args, "--");
            }
        }
        $cmd = $this->ionizer->getPhpCmd() . " -r " . escapeshellarg($code) . " " . implode(" ", $args);
        $this->log->debug($cmd);
        passthru($cmd, $status);
        if ($status > 128) {
            $this->log->debug("Code exited with abnormal status: $status");
        }
        exit($status);
    }

    /**
     * Parse and execute the specified file.
     *
     * Note that there is no restriction on which files can be executed; in particular, the filename is not required have a .php extension.
     * @param string $file
     * @param array $args Any command line arguments for code
     */
    public function runCommand(string $file, ...$args)
    {
        $cmd = $this->ionizer->getPhpCmd() . " -f " . escapeshellarg($file) . " " . implode(" ", $args);
        $this->log->debug($cmd);
        passthru($cmd, $status);
        if ($status > 128) {
            $this->log->debug("Script exited with abnormal status: $status");
        }
        exit($status);
    }

    /**
     * Get and set options
     *
     * @param string $action what you want to do with config. One of: get, set, list
     * @param string $name the option name
     * @param mixed $value new value for 'set' action
     *
     * @example list
     * @example get restart.sleep
     * @example set version.build_os ubuntu-14.04
     */
    public function configCommand(string $action = "list", string $name = "", $value = "")
    {

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