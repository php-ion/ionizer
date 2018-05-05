<?php

namespace Ionizer;

use Ionizer\Actor\Options\OptionsInterface;
use Ionizer\Helper\BaseHelper;
use Koda\ArgumentInfo;
use Koda\ClassInfo;
use Koda\Error\InvalidArgumentException;
use Koda\Handler;

class Ionizer
{

    const VERSION = "0.2.0";

    public $cache_dir;
    public $config_path;
    public $root_dir;
    public $home_dir;
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


    public $argv = [];
    /**
     * @var array
     */
    public $options = [];
    public $command = "";
    public $command_args   = [];

    public $config = [];

    /**
     * @var array index of php-ion variants
     */
    public $index;

    /**
     * @var Log
     */
    public $log;

    /**
     * @var BaseHelper
     */
    public $helper;

    public function getDefaultConfig(): array
    {
        return [
            "restart" => "https://github.com/php-ion/ionizer/wiki/configuration#restart",
            "restart.sleep"    => 0.0,
            "restart.min_wait" => 0.2,
            "restart.attempts" => 0,
            "restart.on_fail"  => "",

            "version" => "https://github.com/php-ion/ionizer/wiki/configuration#version",
            "version.allow_unstable" => false,
            "version.force_build"    => true,
            "version.build_os"       => "auto",

            "start" => "https://github.com/php-ion/ionizer/wiki/configuration#start",
            "start.php_flags"   => "",
            "start.use_starter" => true,
            "start.rewrite_proctitle" => true,
            "start.memory_percent"    => -1,

            "core" => "https://github.com/php-ion/ionizer/wiki/configuration#core",
            "core.cache_dir" => $this->home_dir . "/cache"
        ];
    }

    public function __construct()
    {
        $this->root_dir = dirname(__DIR__);
        $this->home_dir = $_SERVER["HOME"]."/.ionizer.php";
//        $this->cache_dir = $this->root_dir . "/cache";
//        $this->config_path = $this->cache_dir . "/config.json";
//        if (!is_readable($this->cache_dir)) {
//            mkdir($this->cache_dir, 0777, true);
//        }

        $this->argv = $_SERVER["argv"];
        array_shift($this->argv);
        $this->options = CLI::popOptions($this->argv);

        $this->helper = BaseHelper::getInstance($this);
        $this->log    = new Log($this->getOption("level", LOG_DEBUG));
        $this->setConfig($this->getOption("config", $this->home_dir . "/config.json"));
        $this->setCacheDir($this->config["core.cache_dir"]);

//        if (file_exists($this->config_path)) {
//            $this->log->debug("Config {$this->config_path} found");
//            $config = file_get_contents($this->config_path);
//            $config = json_decode($config, true);
//            if (json_last_error()) {
//                $this->log->error("Broken config: ".json_last_error_msg()."\nUse default config");
//                $this->config = self::getDefaultConfig();
//            } else {
//                $this->config = array_merge($config, self::getDefaultConfig());
//            }
//        } else {
//            $this->log->debug("Create new config {$this->config_path}");
//            $this->config = self::getDefaultConfig();
//            $this->flushConfig();
//        }
//        $this->config = self::getDefaultConfig();

//        if ($this->hasOption("help")) {
//            if ($this->command) {
//                $args["command"] = $this->command;
//            }
//            $this->command = "help";
//        }

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
//        $this->link = $this->cache_dir . "/" . $this->link_filename;
        putenv("IONIZER_STARTER=".dirname(__DIR__));

    }

    /**
     * @return string
     */
    public function getCurrentVersionPath()
    {
        if ($this->link && is_link($this->link)) {
            return basename(dirname(readlink($this->link)));
        } else {
            return "";
        }
    }

    /**
     * Set cache directory
     * @param string $path
     */
    public function setCacheDir(string $path)
    {
        if (!is_readable($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new \RuntimeException("Failed to create cache directory $path");
            }
        }
        $this->cache_dir = realpath($path);
        $this->link = $this->cache_dir . "/" . $this->link_filename;
    }

    /**
     * Load config file
     * @param string $path
     */
    public function setConfig(string $path)
    {
        $this->config_path = $path;
        if (file_exists($this->config_path)) {
            $this->log->debug("Config '{$this->config_path}' found");
            if (!is_readable($this->config_path)) {
                throw new \RuntimeException("Config file '{$this->config_path}' not readable (invalid permissions?)");
            }
            $config = file_get_contents($this->config_path);
            $config = json_decode($config, true);
            if (json_last_error()) {
                $this->log->error("Broken config: ".json_last_error_msg()."\nUse default config");
                $this->config = self::getDefaultConfig();
            } else {
                $this->config = array_merge($config, self::getDefaultConfig());
            }
        } else {
            if (!is_dir(dirname($this->config_path))) {
                mkdir(dirname($this->config_path), 0755, true);
            }
            $this->log->debug("Create new config {$this->config_path}");
            $this->config = self::getDefaultConfig();
            $this->flushConfig();
        }
        $this->config_path = realpath($this->config_path);
    }

    /**
     * Write new config
     */
    public function flushConfig()
    {
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) {
            throw new \RuntimeException("Saving configuration failed: json error: " . json_last_error_msg());
        }
        file_put_contents(
            $this->config_path,
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
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
        return isset($this->options[$long]);
    }

    /**
     * @param string $long
     * @param mixed $default
     *
     * @return mixed
     */
    public function getOption(string $long, $default = null)
    {
        return $this->options[$long] ?? $default;
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

    public function gitClone(string $version): string
    {
        if (is_dir($this->cache_dir."/".$version."/repo")) {
            $this->exec(
                "cd {$this->cache_dir}/{$version}/repo && git pull && git checkout $version",
                $clone_log = "{$this->cache_dir}/{$version}/clone.log"
            );
        } else {
            $this->log->debug("Cloning the php-ion repo {$this->git_url} into {$this->cache_dir}/{$version}/repo");
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

        return "{$this->cache_dir}/{$version}/repo";
    }

    private function compile(string $version, string $so_path)
    {
        $this->log->info("Make ion from sources...");
        $repo_path = $this->gitClone($version);
        $this->log->debug("Compile $repo_path");
        $flags = $this->helper->buildFlags();
        try {
            $this->exec(
                ($flags ? "$flags " : "") .
                PHP_BINARY . " {$repo_path}/bin/ionizer.php --build=$so_path",
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

    public function getPhpCmd(): string
    {
        $starter_path = dirname(__DIR__)."/resources/starter.php";
        $ext_path = $this->getExtPath();
        $flags = "-dextension=".escapeshellarg($ext_path)." -dmemory_limit=-1 -dauto_prepend_file=".escapeshellarg($starter_path);
        if ($env = getenv('IONIZER_FLAGS')) {
            $flags .= " " . $env;
        }
        if ($this->config["start.php_flags"]) {
            $flags .= " " . $this->config["start.php_flags"];
        }
        putenv("IONIZER_FLAGS=$flags");
        return "php \$IONIZER_FLAGS";
    }


    public function run()
    {
        $command = CLI::popArgument($this->argv);
        $controller = new Controller($this);
        $handler = new Handler();
        $handler->setFactory(function (ArgumentInfo $inf) {
            if (is_subclass_of($inf->class_hint, OptionsInterface::class)) {
                $class = new ClassInfo($inf->class_hint);
                $class->scan([
                    ClassInfo::METHODS => ClassInfo::FLAG_PUBLIC | ClassInfo::FLAG_NON_STATIC
                ], [
                    ClassInfo::METHODS => "set*Param"

                ]);

//                $params = CLI::scanArgv();
                $object = $class->createInstance([]);
                foreach ($class->methods as $name => $method) {
                    if ($method->hasArguments()) {
                        $method->invoke([], (new Handler())->setContext($object));
                    } else {
                        $method->invoke([], (new Handler())->setContext($object));
                    }
                }

                return $object;
            } else {
                return null;
            }
        });
        try {
            if ($command) {
                if ($controller->info->hasMethod("{$command}Command")) {
                    $method = $controller->info->getMethod("{$command}Command");
                    $argv = [];
                    foreach ($method->args as $arg) {
                        if (is_subclass_of($arg->class_hint, OptionsInterface::class)) {
                            $options = $argv[] = new $arg->class_hint;
                            foreach(CLI::popOptions($this->argv) as $k => $v) {
                                $m = "set" . str_replace("_", "", $k);
                                if (method_exists($options, $m)) {
                                    $options->{$m}($v);
                                } else {
                                    $options->{$k} = $v;
                                }
                            }
                        } else {
                            $arg = CLI::popArgument($this->argv);
                            if ($arg) {
                                $argv[] = $arg;
                            }
                        }
                    }
                    $controller->info->getMethod("{$command}Command")->invoke($argv, $handler->setContext($controller));
                } elseif (file_exists($this->command)) {
                    $controller->runCommand($this->command, ...array_values($this->command_args));
                } else {
                    throw new \InvalidArgumentException("Command '{$this->command}' not found");
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