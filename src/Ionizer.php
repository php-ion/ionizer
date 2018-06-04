<?php

namespace Ionizer;

use Ionizer\Actor\Options\OptionsInterface;
use Ionizer\Component\Config;
use Ionizer\Component\Index;
use Ionizer\Component\Version;
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
    public $build_id;

    public const INDEX_URL = "https://raw.githubusercontent.com/php-ion/builds/master/builds/index.json";
    public const INDEX_HASH_URL = "https://raw.githubusercontent.com/php-ion/builds/master/builds/index.json.sha1";
    public $index_url = "https://raw.githubusercontent.com/php-ion/builds/master/builds/index.json";
    public $so_url_prefix = "https://github.com/php-ion/builds/blob/master/builds";
    public $git_url = "https://github.com/php-ion/php-ion.git";


    public $argv = [];
    /**
     * @var Router
     */
    public $router;
    /**
     * @var array
     */
    public $options = [];

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Index index of php-ion variants
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

    public $binaries = [
        "php"       => PHP_BINARY,
        "phpize"    => 'phpize',
        "make"      => 'make',
        "phpunit"   => 'vendor/bin/phpunit',
        "gdb"       => 'gdb',
        "gdbserver" => 'gdbserver',
        "lcov"      => 'lcov',
        "docker"    => 'docker',
        "strip"     => 'strip'
    ];

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
        $this->home_dir = $_SERVER["HOME"]."/.ionizer-php";

        $this->argv = $_SERVER["argv"];
        array_shift($this->argv);
        $this->options = CLI::popOptions($this->argv);

        $this->helper  = BaseHelper::getInstance($this);
        $this->log     = new Log($this->getOption("level", LOG_DEBUG));
        $this->config  = new Config($this->getOption("config", $this->home_dir . "/config.json"), $this->log);
        $this->setCacheDir($this->config["core.cache_dir"]);
//        $this->current = new Version();
        $this->index   = new Index($this->cache_dir . "/index.json", $this);

        list($os_name, $os_release, $os_family) = $this->helper->getOsName();
        $php_release = implode(".", sscanf(PHP_VERSION, "%d.%d"));
        $php_debug   = PHP_DEBUG || ZEND_DEBUG_BUILD;
        $php_zts     = (bool) PHP_ZTS;

        $build_os = $this->index->getBuildOs($os_name, $os_release, $os_family);

        $this->build_id = $build_os
            . "_php-{$php_release}_"
            . ($php_debug ? "debug" : "non-debug")
            . "_"
            . ($php_zts ? "zts" : "nts")
            . ".so";
        $this->link = $this->cache_dir . "/" . $this->build_id;
        putenv("IONIZER_STARTER=".dirname(__DIR__));

    }

    /**
     * @return string
     */
    public function getBuildID(): string
    {
        return $this->build_id;
    }

    const VER_FORCE = 1;
    const VER_WITH_DIST = 2;

    public function getVersion($version, int $flags = 0): ?Version
    {
        if ($version === "current") {
            if (is_link($this->link)) {
                $dist = readlink($this->link);
            }
        }

        if (file_exists($version)) {

        }
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
        $this->link = $this->cache_dir . "/" . $this->build_id;
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
                    "user_agent" => "ionizer/0.1",
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
//    public function getLastVersionName(): string
//    {
//        return key($this->getIndex()["variants"]) ?: "master";
//    }
//
    /**
     * @param string $version
     * @return array
     */
//    private function getVariantForVersion(string $version): array
//    {
//        $index = $this->getIndex();
//        if (isset($index["variants"][$version])) {
//            if (isset($index["variants"][$version][ $this->link_filename ])) {
//                return $index["variants"][$version][ $this->link_filename ];
//            } else {
//                return [];
//            }
//        } else {
//            throw new \RuntimeException("Not found variant for version $version");
//        }
//    }

    /**
     * @return array
     */
    private function selectVariant(): array
    {
        $index = $this->getIndex();
        $count = 3;
        foreach ($index["variants"] as $version => $variants) {
            if (isset($variants[ $this->build_id ])) {
                $this->log->debug("Found remote build: $version/{$this->build_id}");
                return $variants[ $this->build_id ];
            } else if (file_exists($this->cache_dir . "/$version/" . $this->build_id)) {
                $this->log->debug("Found local build: $version/{$this->build_id}");
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
        $so_path = $this->cache_dir . "/" . $version .  "/" . $this->build_id;
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
        @symlink($version .  "/" . $this->build_id, $this->link);
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
            passthru("($command) 2>&1 1>{$log}", $code);
        } else {
            $this->log->debug("exec: $command");
            passthru("($command) 2>&1 1>{$log}", $code);

        }
        if($code) {
            throw new \RuntimeException("Failed exec: $command");
        }
    }

    public function bin(string $name): string
    {

    }

    /**
     * Returns gdb start command
     * @return string
     */
    public function getGdbCmd(): string
    {
        return $this->bin("gdb")
            . ' -ex "handle SIGHUP nostop SIGCHLD nostop" -ex "run" -ex "thread apply all bt"'
            . ' -ex "set pagination 0" -batch -return-child-result -silent --args';
    }

    /**
     * @param bool $starter
     * @param string $ext_path
     * @return string
     */
    public function getPhpCmd(bool $starter = true, string $ext_path = ""): string
    {
        $starter_path = dirname(__DIR__)."/resources/starter.php";
        $ext_path = $ext_path ?: $this->getExtPath();
        $flags = "-dextension=".escapeshellarg($ext_path);
        if ($this->options["noini"] ?? false) {
            $flags .= " -n";
        }
        if ($starter) {
            $flags .= " -dauto_prepend_file=".escapeshellarg($starter_path);
        }
        if ($env = getenv('IONIZER_FLAGS')) {
            $flags .= " " . $env;
        }
        if ($this->config["start.php_flags"]) {
            $flags .= " " . $this->config["start.php_flags"];
        }
//        putenv("IONIZER_FLAGS=$flags");
        if (empty($this->options["debug"])) {
            return "php $flags";
        } else {
            return $this->getGdbCmd() . " php $flags";
        }
    }

    public function php(string $args, int $flags = 0, string $ext_path = "")
    {
        $this->exec($this->getPhpCmd($flags, $ext_path) . " " . $args);
    }


    public function run()
    {
        $router = new Router($this);
        $router->run($this->argv, Controller::class);
    }
}