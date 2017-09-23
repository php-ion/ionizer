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

    /**
     * @var array
     */
    public $options = [];
    public $command = "";
    public $args   = [];

    /**
     * @var array index of php-ion variants
     */
    public $index;

    /**
     * @var Log
     */
    public $log;

    /**
     * @var HelperAbstract
     */
    public $helper;

    public function __construct()
    {
        $this->root_dir = dirname(__DIR__);
        $this->cache_dir = $this->root_dir . "/cache";
        if (!is_readable($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
        $this->helper = HelperAbstract::getInstance($this);
        $this->log  = new Log($this->getOption("level", LOG_DEBUG));

        list($this->options, $this->command, $this->args) = $this->scanArgv();
        if ($this->hasOption("help")) {
            if ($this->command) {
                $args["command"] = $this->command;
            }
            $this->command = "help";
        }

        list($os_name, $os_release, $os_family) = $this->helper->getOsName();
        $php_release = implode(".", sscanf(PHP_VERSION, "%d.%d"));
        $php_debug   = PHP_DEBUG || ZEND_DEBUG_BUILD;
        $php_zts     = (bool) PHP_ZTS;

        $build_os = $this->getBuildOs($os_name, $os_release, $os_family);

        $this->link_filename = $build_os
            . "_php-{$php_release}_"
            . ($php_debug ? "debug" : "non-debug")
            . "_"
            . ($php_zts ? "zts" : "nts")
            . ".so";
        $this->link = $this->cache_dir . "/" . $this->link_filename;
        putenv("IONIZER_STARTER=".dirname(__DIR__));

    }

    public function getBuildOs(string $os_name, string $os_release, string $os_family) : string
    {
        $index = $this->getIndex();
        if (isset($index["os"][$os_family])) {
            foreach ($index["os"][$os_family] as $mask => $build_os) {
                if(fnmatch($mask, "$os_name-$os_release")) {
                    return $build_os;
                }
            }
        }

        return $os_name . "-" . $os_release;
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
            $this->index = $json;
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

    public function getMemoryLimit() : string
    {
        return "-1";
    }

    /**
     * Returns last actual version of ion or master
     * @return string
     */
    public function getLastVersionName(): string
    {
        return key($this->getIndex()["variants"]) ?: "master";
    }

    /**
     * @param string $version
     * @return array
     */
    private function getVariantForVersion(string $version): array
    {
        $index = $this->getIndex();
        if (isset($index["variants"][$version])) {
            if (isset($index["variants"][$version][ $this->link_filename ])) {
                return $index["variants"][$version][ $this->link_filename ];
            } else {
                return [];
            }
        } else {
            throw new \RuntimeException("Not found variant for version $version");
        }
    }

    /**
     * @return array
     */
    private function selectVariant(): array
    {
        $index = $this->getIndex();
        $count = 3;
        foreach ($index["variants"] as $version => $variants) {
            if (isset($variants[ $this->link_filename ])) {
                $this->log->debug("Found remote build: $version/{$this->link_filename}");
                return $variants[ $this->link_filename ];
            } else if (file_exists($this->cache_dir . "/$version/" . $this->link_filename)) {
                $this->log->debug("Found local build: $version/{$this->link_filename}");
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
        if ($version) {
            $variant = $this->getVariantForVersion($version);
        } elseif ($variant = $this->selectVariant()) {
            $version = $variant["version"];
        } else {
            $version = $this->getLastVersionName();
        }
        $so_path = $this->cache_dir . "/" . $version .  "/" . $this->link_filename;
        if ($variant) {
            if (!file_exists($so_path)) {
                $this->log->debug("Download object {$this->so_url_prefix}/{$variant["path"]}?raw=true\n  to $so_path");
                mkdir(dirname($so_path));
                $this->download("{$this->so_url_prefix}/{$variant["path"]}?raw=true", $so_path);
            } else {
                $this->log->debug("Object $so_path found.");
            }
        } else {
            $this->compile($version, $so_path);
        }
        $this->log->debug("Create symlink {$this->link}");
        @symlink($version .  "/" . $this->link_filename, $this->link);
        if (!file_exists($this->link)) {
            throw new \RuntimeException("Could not create symlink {$this->link} -> {$so_path}");
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

    private function compile(string $version, string $so_path)
    {
        $this->log->info("Make ion from sources...");
        if (is_dir($this->cache_dir."/".$version."/repo")) {
            $this->exec(
                "cd {$this->cache_dir}/{$version}/repo && git pull && git checkout $version",
                $clone_log = "{$this->cache_dir}/{$version}/clone.log"
            );
        } else {
            $this->log->debug("Clone php-ion repo {$this->git_url} into {$this->cache_dir}/{$version}/repo");
            mkdir($this->cache_dir."/".$version);
            try {
                $this->exec(
                    "git clone --depth=1 {$this->git_url} --branch $version --single-branch {$this->cache_dir}/{$version}/repo",
                    $clone_log = "{$this->cache_dir}/{$version}/clone.log"
                );
            } catch (\Throwable $e) {
                throw new \RuntimeException("Repo clone failed. See log $clone_log");
            }
        }
        $this->log->debug("Compile {$this->cache_dir}/{$version}/repo");
        $flags = $this->helper->buildFlags();
        try {
            $this->exec(
                ($flags ? "$flags " : "") .
                PHP_BINARY . " {$this->cache_dir}/{$version}/repo/bin/ionizer.php --build=$so_path",
                $build_log = "{$this->cache_dir}/{$version}/build.log"
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException("Compile failed. See log $build_log. " .
                "Also see known troubleshooting https://github.com/php-ion/php-ion/blob/master/docs/install.md");
        }

    }

    private function exec(string $command, string $log = "")
    {
        if ($log) {
            $this->log->debug("exec: $command\n    Log: $log");
            exec("($command) 2>&1 1>{$log}", $out, $code);
        } else {
            $this->log->debug("exec: $command");
            exec("($command) 2>&1 1>{$log}", $out, $code);

        }
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
            if($command) {
                $value = $arg;
            } elseif ($arg[0] == "-") {
                if (strlen($arg) > 1) {
                    if($arg[1] == "-") { // long option
                        $arg = trim($arg, "-");
                        list($key, $value) = explode("=", $arg) + ["", true];
                    } else { // short option
                        $key = trim($arg, "-");
                        $value = true;
                    }
                } else {
                    $value = "-";
                }
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
        $ext_path = $this->getExtPath();
        $flags = "-dextension=".escapeshellarg($ext_path)." -dmemory_limit=-1";
        if ($env = getenv('IONIZER_FLAGS')) {
            $flags .= " " . $env;
        }
        putenv("IONIZER_FLAGS=$flags");
        return "php \$IONIZER_FLAGS";
    }


    public function run()
    {
        $controller = new Controller($this);

        try {
            if ($this->command) {
                if ($controller->info->hasMethod("{$this->command}Command")) {
                    $controller->info->getMethod("{$this->command}Command")->invoke($this->args, (new Handler())->setContext($controller));
                } elseif(file_exists($this->command)) {
                    $controller->runCommand($this->command, ...array_values($this->args));
                } else {
                    throw new \LogicException("Command '{$this->command}' not found");
                }
            }  else {
                $controller->helpCommand();
            }
        } catch (InvalidArgumentException $e) {
            $this->log->error("Required argument '" . $e->argument->name . "' (see: ion help {$this->command})");
        } catch (\Throwable $e) {
            $this->log->error($e);
        }
    }
}