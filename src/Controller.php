<?php

namespace Ionizer;


use Ionizer\Actor\Builder;
use Ionizer\Actor\Options\BuildOptions;
use Ionizer\Actor\Options\OptionsInterface;
use Ionizer\Actor\Options\ServerOptions;
use Ionizer\Actor\Options\TestOptions;
use Ionizer\Actor\Tester;
use Ionizer\Component\Version;
use Ionizer\Helper\BaseHelper;
use Koda\ArgumentInfo;
use Koda\ClassInfo;
use Koda\PropertyInfo;

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

    /**
     * Controller constructor.
     * @param Ionizer $ionizer
     * @throws \Koda\Error\ClassNotFound
     */
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
     * Displays help for a command or list of commands
     * @param string $command Show help for this command
     * @throws \Koda\Error\ClassNotFound
     */
    public function helpCommand(string $command = "")
    {
        $help = [];
        $padding = sprintf("  %-10s // ", "");
        if ($command) {
            if (!$this->info->hasMethod($command."Command")) {
                throw new \LogicException("Command '$command' not found");
            }
            $method = $this->info->getMethod($command."Command");
            $args = [];
            foreach ($method->args as $name => $arg) {
                /** @var ArgumentInfo $arg */
                if ($arg->optional) {
                    if ($arg->variadic) {
                        $args[] = "[<$name> ...]";
                    } else {
                        $args[] = "[<$name>]";
                    }
                } else {
                    $args[] = "<$name>";
                }
            }
            $help[] = "<cli:yellow>Usage:</cli>";
            $help[] = "  ion $command " . implode(" ", $args);
            $help[] = "";
            if ($method->args) {
                $options = [];
                $help[] = "<cli:yellow>Arguments:</cli>";
                foreach ($method->args as $name => $arg) {
                    if ($arg->class_hint) {
                        if (is_subclass_of($arg->class_hint, OptionsInterface::class)) {
                            $class = new ClassInfo($arg->class_hint);
                            $class->scan([
                                ClassInfo::PROPS => ClassInfo::FLAG_NON_STATIC | ClassInfo::FLAG_PUBLIC
                            ]);
                            foreach ($class->properties as $property) {
                                /** @var PropertyInfo $property */
                                if ($property->type) {
                                    $type = $property->type;
                                } elseif ($property->default !== null) {
                                    $type = gettype($property->default);
                                } else {
                                    $type = "MIXED";
                                }

                                if ($type == "boolean" || $type == "bool") {
                                    $value = "";
                                } else {
                                    $value = "=".strtoupper($type);
                                }

                                $options[] = CLI::sprintf("  <cli:green>%-20s</cli> // %s",
                                    "--".$property->name . $value, $property->desc);
                            }
                        }
                    } else {
                        $desc = rtrim($arg->getDescription(), "."). ".";
                        // add padding
                        $desc = str_replace("\n", "\n$padding", $desc);
                        $help[] = CLI::sprintf("  <cli:green>%-10s</cli> // %s", $name, $desc);
                    }
                }

                if ($options) {
                    $help[] = "";
                    $help[] = "<cli:yellow>Options:</cli>";

                    foreach ($options as $opt) {
                        $help[] = $opt;
                    }
                }
            } else {
                $help[] = "<cli:yellow>No arguments</cli>";
            }
            $help[] = "";
            $help[] = "<cli:yellow>About:</cli>";
            $help[] = "  ".str_replace("\n", "\n  ", $method->getDescription());
        } else {
            $help[] = "<cli:yellow>Usage:</cli>";
            $help[] = "  ion [options] command [arguments]";
            $help[] = "  ion [options] <file> [<args> ...]  # run php script (see `run` command)";
            $help[] = "";
            $help[] = "<cli:yellow>Help:</cli>";
            $help[] = "  ion help";
            $help[] = "  ion help <command>";
            $help[] = "";
            $help[] = "<cli:yellow>Commands:</cli>";
            foreach ($this->info->methods as $name => $info) {
                $name = str_replace("Command", "", $name);
                if (strpos($info->getDescription(), "\n")) {
                    $desc = strstr($info->getDescription(), "\n", true);
                } elseif (strpos($info->getDescription(), ". ")) {
                    $desc = strstr($info->getDescription(), ".", true);
                } else {
                    $desc = $info->getDescription();
                }
                $help[] = sprintf("  <cli:green>%-10s</cli> // %s", $name, $desc);
            }
            $help[] = "";
            $help[] = "<cli:yellow>Options:</cli>";
            $help[] = CLI::sprintf(
                "  <cli:green>%-10s</cli>  // %s",
                "--config=PATH", "Config path. Current path is: " . $this->ionizer->config_path);
            $help[] = CLI::sprintf(
                "  <cli:green>%-10s</cli>  // %s",
                "--stderr=PATH", "Write STDERR to specific path (only for commands <eval> and <run>)");
            $help[] = CLI::sprintf(
                "  <cli:green>%-10s</cli>  // %s",
                "--stdout=PATH", "Write STDOUT to specific path (only for commands <eval> and <run>)");
            $help[] = CLI::sprintf(
                "  <cli:green>%-10s</cli>     // %s",
                "--verbose", "Increase the verbosity of messages");
            $help[] = CLI::sprintf(
                "  <cli:green>%-10s</cli>     // %s",
                "--debug", "Starts PHP via gdb (gdb should be installed)");
        }

        echo CLI::parse(implode("\n", $help))."\n";
    }

    /**
     * Show summary info
     * @param string $ion version or branch or commit or path to ion repo (e.g. 0.8.3, master, 33b1e417, /tmp/ion-src).
     *                    If not present actual version will be used.
     */
    public function infoCommand(string $ion = "")
    {
        $info = [];
        $version = $this->ionizer->getVersion($ion ?: "");
        $builder = new Builder("/tmp", $this->ionizer, new BuildOptions());
        [$os_name, $os_version, $os_family] = $this->ionizer->helper->getOsName();

        $info["ION"] = [
            "version"  => "",
            "binary"   => "",
            "build_id" => $this->ionizer->getBuildID()
        ];
        if ($version) {
            $info["ION"]["version"] = $version->name ?: $version->repo_path;
            $info["ION"]["binary"] = $version->ext_path;
        }
        $build_flags = 0;

        if ($this->ionizer->options["debug"] ?? false) {
            $build_flags |= BaseHelper::BUILD_DEBUG;
        }

        $info["PHP"] = [
            "version"    => PHP_VERSION,
            "zend_version" => zend_version(),
            "extension_dir" => PHP_EXTENSION_DIR,
            "debug"      => (bool) PHP_DEBUG || ZEND_DEBUG_BUILD,
            "zts"        => (bool) PHP_ZTS,
            "cmd"        => $version ? $this->ionizer->getPhpCmd(true, $version) : "",
            "phpunit"    => trim(`which phpunit`),
            "composer"   => trim(`which composer`)
        ];
        $info["OS"] = [
            "family"     => $os_family,
            "name"       => $os_name,
            "version"    => $os_version,
            "home"       => $_SERVER["HOME"],
            "uname"      => php_uname('a'),
            "cpu_count"  => $this->ionizer->helper->getCPUCount(),
            "cores_path" => $this->ionizer->helper->getCoreDumpPath(),
            "cores_size" => trim(`ulimit -c`),
            "fd_limit"   => trim(`ulimit -n`)
        ];
        $info["IONIZER"] = [
            "version"     => $this->ionizer::VERSION,
            "config_file" => $this->ionizer->config->getPath(),
            "cache_dir"   => $this->ionizer->cache_dir,
            "cflags"      => getenv("CFLAGS") ?: "",
            "ldflags"     => getenv("LDFLAGS") ?: "",
            "build_env"   => $this->ionizer->helper->buildFlags($build_flags),
            "gdb_cmd"     => $builder->gdb_cmd . " ...",
            "php_cmd"     => PHP_BINARY . " " . getenv('IONIZER_FLAGS') . " ..."
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

    /**
     * Describe an extension
     *
     * @param string $what version or branch or commit or path to ion repo (e.g. 0.8.3, master, 33b1e417, /tmp/ion-src)
     */
    public function descCommand(string $what = "")
    {
        $version = $this->ionizer->getVersion($what);

        if (!$version) {
            throw new \InvalidArgumentException("Seems $what doesn't exists or not ION repository");
        }

        if (!file_exists($version->ext_path)) {
            throw new \InvalidArgumentException("Extension not compiled: object {$version->ext_path} not found.\n"
                . "Run `ion build $what` before testing. See `ion help build`");
        }
        passthru("php -dextension=" . escapeshellarg($version->ext_path) . " " . dirname(__DIR__) . "/resources/describe.php");
    }

    /**
     * Show available version
     *
     * @param bool $all (is a) Show all available versions, including versions in the repository
     */
    public function versionsCommand(bool $all = false)
    {
        $current = "";

        if ($all) {
            $versions = $this->ionizer->getVersions();
        } else {
            $versions = $this->ionizer->index->getVersions(true);
        }
        foreach ($versions as $name => $version) {
            $tags = [];
            /* @var Version $version */
            if ($version->isDraft()) {
                $tags[] = "Draft";
            }
            if ($version->hasBinary()) {
                $tags[] = "Has build";
            }
            if ($version->isBranch()) {
                $tags[] = "Branch";
            }
            if ($current == $name) {
                $marker = "*";
            } elseif (file_exists($version->ext_path)) {
                $marker = "v";
            } else {
                $marker = " ";
            }
            if ($tags) {
                $tags = "[".implode(", ", $tags)."]";
            } else {
                $tags = "";
            }
            echo " $marker $name $tags\n";
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
     * Note that there is no restriction on which files can be executed; in particular,
     * the filename is not required have a .php extension.
     * @param string $file Valid PHP script
     * @param array $args Any command line arguments for file
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
     * @param string $action what you want to do with config. One of: get, set
     * @param string $name the option name
     * @param mixed $value new value for 'set' action
     *
     * @example get restart.sleep
     * @example set version.build_os ubuntu-14.04
     */
    public function configCommand(string $action = "", string $name = "", $value = "")
    {
        if ($action == "get") {
            if (isset($this->ionizer->config[$name])) {
                echo json_encode($this->ionizer->config[$name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } else {
                throw new \InvalidArgumentException("Config option <$name> not found");
            }
        } elseif ($action == "set") {
            if (isset($this->ionizer->config[$name])) {
                settype($value, gettype($this->ionizer->config[$name]));
                $this->ionizer->config[$name] = $value;
                $this->ionizer->config->flush();
            } else {
                throw new \InvalidArgumentException("Config option <$name> not found");
            }
        } else {
            foreach ($this->ionizer->config->getAll() as $key => $value) {
                echo $key . " = " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
            }
        }
    }

    /**
     * Test current ion extension
     * @param string $what version or branch or commit or path to ion repo (e.g. 0.8.3, master, 33b1e417, /tmp/ion-src)
     * @param TestOptions $options
     */
    public function testCommand(string $what, TestOptions $options)
    {

        $php_args     = [];
        $phpunit_args = [];

        $version = $this->ionizer->getVersion($what);

        if (!$version) {
            throw new \InvalidArgumentException("Seems $what doesn't exists or not ION repository");
        }

        if (!file_exists($version->ext_path)) {
            throw new \InvalidArgumentException("Extension not compiled: object {$version->ext_path} not found.\n"
                . "Run `ion build $what` before testing. See `ion help build`");
        }

        if (!is_dir($version->repo_path)) {
            $this->ionizer->fetchRepo($version);
        }

        if ($this->ionizer->options["debug"] ?? false) {
            $php_args[] = "-e";
        }

        if ($options->group) {
            $phpunit_args[] = "--group=" . escapeshellarg($options->group);
        }

        if (file_exists($version->repo_path . "/vendor/bin/phpunit")) {
            $phpunit = $version->repo_path . "/vendor/bin/phpunit";
        } else {
            $phpunit = $this->ionizer->bin("phpunit");
        }


        $this->ionizer->php(
            implode(" ", $php_args) . " "  . $phpunit . " " . implode($phpunit_args),
            false,
            $version
        );
    }

    /**
     * Starts async web server
     *
     * If a PHP file is given on the command line when the web server is started it is treated as a "router" script.
     * The script is run at the start of each HTTP request.
     *
     * If a class name is given on the command line when the web server is started it is treated as a "router" class.
     * The router object was created once when the server starts. The methods is run at the start of each HTTP request.
     *
     * @param string $router PHP script or class
     * @param ServerOptions $options
     */
    public function serverCommand(string $router, ServerOptions $options)
    {

    }

    /**
     * Build the php-ion from source
     *
     * @param string $what version or branch or commit or path to ion repo (e.g. 0.8.3, master, 33b1e417, /tmp/ion-src)
     * @param BuildOptions $options build options
     * @example /tmp/ion-src --build=/tmp/ion.so
     * @example master --debug --gdb --build --test
     */
    public function buildCommand(string $what, BuildOptions $options)
    {
        if ($path = realpath($what)) { // build by path
            $builder = new Builder($path, $this->ionizer, $options);
        } else { // build commit
            $path = $this->ionizer->gitClone($what);
            $builder = new Builder($path, $this->ionizer, $options);
        }
        $this->log->debug("Build from source $path");
        $builder->run();
    }

}