<?php

namespace Ionizer;

use Koda\ClassInfo;
use Koda\Error\InvalidArgumentException;
use Koda\Handler;

class Ionizer
{

    public $cache_dir;
    public $root_dir;
    public $env;

    /**
     * @var ClassInfo
     */
    public $class;

    public $link;
    public $link_filename;

    public $index_url = "https://raw.githubusercontent.com/php-ion/builds/master/builds/index.json";
    public $so_url_prefix = "https://github.com/php-ion/builds/blob/master/builds";
    public $git_url = "https://github.com/php-ion/php-ion.git";

    public $options = [];

    /**
     * @var array index of php-ion variants
     */
    public $index;

    /**
     * @var Log
     */
    public $log;

    public function __construct()
    {
        $this->root_dir = dirname(__DIR__);
        $this->cache_dir = $this->root_dir . "/cache";
        if (!is_readable($this->cache_dir)) {
            throw new \RuntimeException("ION Wrapper: cache directory {$this->cache_dir} in not readable");
        }
        $this->env = $this->getEnvInfo();
        $this->link_filename = $this->env["os"]
            . "_php-{$this->env["php"]}_"
            . ($this->env["debug"] ? "debug" : "non-debug")
            . "_"
            . ($this->env["zts"] ? "zts" : "nts")
            . ".so";
        $this->link = $this->cache_dir . "/" . $this->link_filename;

    }

    /**
     * Checks whether the parameter
     *
     * @param string $long
     *
     * @param string $short
     * @return bool
     */
    public function hasOption(string $long, string $short = "")
    {
        $options = getopt($short, [$long]);
        return isset($options[$long]);
    }

    /**
     * @param string $long
     * @param mixed $default
     *
     * @return mixed
     */
    public function getOption(string $long, $default = null)
    {
        $options = getopt("", [$long . "::"]);
        if (isset($options[$long])) {
            return $options[$long];
        } else {
            return $default;
        }
    }

    public function progressStart(string $message)
    {
        fwrite(STDERR, "[ION] $message");
    }

    public function progressFinish()
    {
        fwrite(STDERR, " OK\n");
    }

    public function getPHPInfo(): array
    {
        list($major, $minor) = explode(".", PHP_VERSION, 3);
        return [
            $major . "." . $minor,
            PHP_DEBUG ? "debug" : "non-debug",
            PHP_ZTS ? "zts" : "nts"
        ];
    }

    public function isLinux()
    {
        return strtolower(PHP_OS) == "linux";
    }

    public function isMacOS()
    {
        return strtolower(PHP_OS) == "darwin";
    }

    /**
     * Get index of builds
     *
     * @param bool $refresh fetch new index and save in cache
     * @return array
     */
    public function getIndex(bool $refresh = false): array
    {
        if ($refresh) {
            $context = stream_context_create([
                "http" => [
                    "user_agent" => "ion-wrapper/0.1",
                    "headers" => "Accept: */*"
                ]
            ]);
            $this->log->debug("Fetch new index from {$this->index_url}");
            $response = file_get_contents($this->index_url, false, $context);
            $json = json_decode($response, true);
            if (json_last_error()) {
                throw new \RuntimeException("Broken json index from {$this->index_url}: " . json_last_error_msg());
            }
            $this->log->debug("Store index to cache {$this->cache_dir}/index.json");
            file_put_contents($this->cache_dir . "/index.json", $response);
            $this->index = $response;
            return $json;
        } elseif ($this->index) {
            return $this->index;
        } elseif (file_exists($this->cache_dir . "/index.json")) {
            $this->log->debug("Read index from cache {$this->cache_dir}/index.json");
            return $this->index = json_decode(file_get_contents($this->cache_dir . "/index.json"), true);
        } else {
            $this->log->debug("Index mismatch");
            return $this->getIndex(true);
        }
    }

    /**
     * Get information about PHP and OS
     * @return array
     */
    public function getEnvInfo() : array
    {
        if ($this->isLinux()) {
            // by default for all linuxes uses build for ubuntu
            $os = "ubuntu-16.04";
            $os_family = "linux";
        } elseif ($this->isMacOS()) {
            // scan major version of darwin
            $os = "darwin-" . strstr(php_uname("r"), ".", true);
            $os_family = "macos";
        } else {
            throw new \LogicException("Unsupported OS (linux or macos only)");
        }
        return [
            "os" => $os,
            "os_family" => $os_family,
            "php" => implode(".", sscanf(PHP_VERSION, "%d.%d")),
            "debug" => PHP_DEBUG || ZEND_DEBUG_BUILD,
            "zts" => (bool) PHP_ZTS
        ];
    }

    public function getMemoryLimit() : string
    {
        return "-1";
    }

    private function getVariantForVersion(string $version): array
    {
        $index = $this->getIndex();
        if (isset($index["variants"][$version][ $this->link_filename ])) {
            return $index["variants"][$version][ $this->link_filename ];
        } else {
            throw new \RuntimeException("Not found variant for version $version");
        }
    }

    /**
     * @param bool $allow_older
     * @return array
     */
    private function selectVariant($allow_older = false): array
    {
        $index = $this->getIndex();
        $count = 3;
        foreach ($index["variants"] as $version => $variants) {
            if (isset($variants[ $this->link_filename ])) {
                $this->log->debug("Found acceptable variant: $version/{$this->link_filename}");
                return $variants[ $this->link_filename ];
            } else if (file_exists($this->cache_dir . "/$version/" . $this->link_filename)) {
                $this->log->debug("Found build: $version/{$this->link_filename}");
                return ["version" => $version];
            }
            if(!$count--) {
                break;
            }
        }

        return [];
    }

    /**
     *
     * @param string $version
     */
    public function selectVersion(string $version = "")
    {
        $this->log->debug("Link {$this->link} not found. Resolve problem.");
        if ($version) {
            $variant = $this->getVariantForVersion($version);
        } else {
            $variant = $this->selectVariant();
        }
        if ($variant) {
            $so_path = $this->cache_dir . "/" . $variant["version"] .  "/" . $this->link_filename;
            if (!file_exists($so_path)) {
                $this->log->debug("Download object {$this->so_url_prefix}/{$variant["path"]}?raw=true\n  to $so_path");
                mkdir(dirname($so_path));
                $this->download("{$this->so_url_prefix}/{$variant["path"]}?raw=true", $so_path);
            } else {
                $this->log->debug("Object $so_path found.\n  Create symlink {$this->link}");
            }
            symlink($variant["version"] . "/" . basename($so_path), $this->link);
        } else {
            $this->compile($version);
        }
    }

    public function getExtPath(bool $autoload = true) : string
    {
        if (!file_exists($this->link) && $autoload) {
            $this->log->debug("Link {$this->link} not found. Resolve problem.");
            $this->selectVersion();
        }
        return $this->link;
    }

    private function download(string $url, string $path)
    {
        $context = stream_context_create([
            "http" => [
                "user_agent" => "ion-wrapper/0.1",
                "headers" => "Accept: */*"
            ],
//            "notification" => [$this, "downloadNotify"]
        ]);
        $this->log->progressStart('Downloading');
        $binary = file_get_contents($url, false, $context);
        $this->log->progressEnd();
        if ($binary) {
            file_put_contents($path, $binary);
        } else {
            throw new \RuntimeException("Failed to download $url");
        }
    }

    private function compile(string $version)
    {
        try {
            $this->log->debug("Clone php-ion repo {$this->git_url} into {$this->cache_dir}/{$version}/repo");
            mkdir($this->cache_dir."/".$version);
            $this->exec(
                "git clone --depth=1 {$this->git_url} --branch $version --single-branch {$this->cache_dir}/{$version}/repo",
                "{$this->cache_dir}/{$version}/clone.log"
            );
            $this->log->debug("Compile {$this->cache_dir}/{$version}/repo");
            $this->exec(
                PHP_BINARY . " {$this->cache_dir}/{$version}/repo/bin/ionizer.php --build={$this->cache_dir}/{$version}/{$this->link_filename}.so",
                "{$this->cache_dir}/{$version}/build.log"
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException("");
        }
    }

    private function exec(string $command, string $log) {
        $this->log->debug("exec: $command");
        exec("($command) 2>&1 1>{$log}", $out, $code);
        if($code) {
            throw new \RuntimeException("Failed exec: $command");
        }
    }


    private function scanArgv() : array
    {
        $options = [];
        $command = "";
        $args    = [];
        $cli_args = $_SERVER["argv"];
        array_shift($cli_args);
        foreach($cli_args as $arg) {
            $key = "";
            $value = null;
            if ($arg[0] == "-") {
                if($arg[1] == "-") { // long option
                    $arg = trim($arg, "-");
                    list($key, $value) = explode("=", $arg) + ["", true];
                } else { // short option
                    $key = trim($arg, "-");
                    $value = true;
                }
            } elseif($command) {
                $value = $arg;
            } else {
                $command = $arg;
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

    public function getPhpCmd(): string
    {
        $memory = $this->getMemoryLimit();
        $ext_path = $this->getExtPath();

        return "IONIZER=1 " . PHP_BINARY .  " -dmemory_limit=$memory -dextension=$ext_path";
    }


    public function run()
    {
        list($this->options, $command, $args) = $this->scanArgv();
        if ($this->hasOption("help")) {
            if ($command) {
                $args["command"] = $command;
            }
            $command = "help";
        }
        $this->log = new Log($this->getOption("level", LOG_DEBUG));
        $controller = new Controller($this);

        try {
            if ($command) {
                if ($controller->info->hasMethod("{$command}Command")) {
                    $controller->info->getMethod("{$command}Command")->invoke($args, (new Handler())->setContext($controller));
                } else {
                    throw new \LogicException("Command '$command' not found");
                }
            } else {
                $controller->helpCommand();
            }
        } catch (InvalidArgumentException $e) {
            $this->log->error("Required parameter '" . $e->argument->name . "' (see ion help $command)");
        } catch (\Throwable $e) {
            $this->log->error($e);
        }
    }
}