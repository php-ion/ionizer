<?php

namespace Ionizer;

use Ionizer\Component\Config;
use Ionizer\Component\Index;
use Ionizer\Component\Version;
use Ionizer\Helper\BaseHelper;
use Koda\ClassInfo;

class Ionizer
{

    const VERSION = "0.2.0";

    public $cache_dir;
    public $root_dir;
    public $home_dir;
    public $env;

    /**
     * @var ClassInfo
     */
    public $class;

    public $build_id;

    public const INDEX_URL  = "https://raw.githubusercontent.com/php-ion/builds/master/builds/index.json";
    public const BUILDS_URL = "https://github.com/php-ion/builds/blob/master/builds";
    public const GIT_URL    = "https://github.com/php-ion/php-ion.git";


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
        "lcov"      => 'lcov',
        "strip"     => 'strip'
    ];


    public function __construct()
    {
        $this->root_dir = dirname(__DIR__);
        $this->home_dir = $_SERVER["HOME"]."/.ionizer-php";
        $this->cache_dir = $this->home_dir . "/cache";

        if (!is_dir($this->home_dir)) {
            mkdir($this->home_dir, 0755);
        }
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755);
        }

        $this->argv = $_SERVER["argv"];
        array_shift($this->argv);
        $this->options = CLI::popOptions($this->argv);

        $this->helper  = BaseHelper::getInstance($this);
        $this->log     = new Log($this->getOption("level", LOG_DEBUG));
        $this->config  = new Config($this->getOption("config", $this->home_dir . "/config.json"), $this->log);
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
            . ($php_zts ? "zts" : "nts");
//        $this->link = $this->cache_dir . "/" . $this->build_id;
        putenv("IONIZER_STARTER=".dirname(__DIR__));

    }

    /**
     * @return string
     */
    public function getBuildID(): string
    {
        return $this->build_id;
    }


    public function getVersions(): array
    {
        $versions = [];
        $response = $this->httpGET("https://api.github.com/repos/php-ion/php-ion/releases");
        $json = json_decode($response, true);
        foreach ($json as $release) {

            if ($this->index->hasVersion($release["tag_name"])) {
                $version = $this->index->getVersion($release["tag_name"]);
            } else {
                $version = new Version($this);
                $version->setVersionID($release["tag_name"]);
            }
            if ($release["draft"]) {
                $version->flags |= Version::DRAFT;
            }
            $versions[$version->name] = $version;
        }

        $response = $this->httpGET("https://api.github.com/repos/php-ion/php-ion/branches");
        $json = json_decode($response, true);
        foreach ($json as $branch) {
            if ($this->index->hasVersion($branch["name"])) {
                $version = $this->index->getVersion($branch["name"]);
            } else {
                $version = new Version($this);
                $version->setVersionID($branch["name"]);
            }
            $version->flags |= Version::DRAFT;
            $version->setVersionID($branch["name"]);
            $versions[$version->name] = $version;
        }

        return $versions;
    }

    /**
     * @param string $version
     * @return Version|null
     */
    public function getVersion(string $version = ""): ?Version
    {
        $v = new Version($this);
        if (!$version) {
            if (!file_exists($this->home_dir . "/actual")) {
                $this->log->debug("No one actual ION build found. Resolve problem automatically.");
                $version = $this->index->getLastPossibleVersion();
                if ($version) {
                    $this->setActualVersion($version);
                    $v = $version;
                } else {
                    $this->log->notice("No one actual ION build found. Build ION with `ion build` command (see `ion help build`)");
                    return null;
                }
            } else {
                $link = readlink($this->home_dir . "/actual");
                $this->log->debug("Actual version is $link");
                if (is_dir($this->cache_dir . "/" . $link)) {
                    $v->setVersionID($link);
                } else {
                    $v = $this->getVersion($link);
                }
            }
        } elseif (is_dir($version)) {

            if (!file_exists($version . "/composer.json")) {
                $this->log->debug("$version does not contain the file composer.json");
                return null;
            }
            $composer = json_decode(file_get_contents($version . "/composer.json"), true);
            if ($composer["name"] != "phpion/phpion") {
                $this->log->warning("$version is not valid ION repository");
                return null;
            }
            $v->setVersionPath($version);
        } elseif (is_file($version)) {
            return null;
        } else {
            throw new \RuntimeException("$version unsupported yet");
        }
        return $v;
    }

    public function setActualVersion(Version $version)
    {
        $this->log->debug("Setup $version as actual version");
        symlink($version->name, $this->home_dir."/actual");
    }

    /**
     * Checks whether the parameter
     *
     * @param string $long
     *
     * @return bool
     */
    public function hasOption(string $long)
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
//    public function getIndex(bool $refresh = false): array
//    {
//        if ($refresh) {
//            $context = stream_context_create([
//                "http" => [
//                    "user_agent" => "ionizer/0.1",
//                    "headers" => "Accept: */*"
//                ]
//            ]);
//            $this->log->debug("Fetch new index from {$this->index_url}");
//            $response = file_get_contents($this->index_url, false, $context);
//            $json = json_decode($response, true);
//            if (json_last_error()) {
//                throw new \RuntimeException("Broken json index from {$this->index_url}: " . json_last_error_msg());
//            }
//            $this->log->debug("Store index to cache {$this->cache_dir}/index.json");
//            file_put_contents($this->cache_dir . "/index.json", $response);
//            $this->index = $json;
//            return $json;
//        } elseif ($this->index) {
//            return $this->index;
//        } elseif (file_exists($this->cache_dir . "/index.json")) {
//            $this->log->debug("Read index from cache {$this->cache_dir}/index.json");
//            return $this->index = json_decode(file_get_contents($this->cache_dir . "/index.json"), true);
//        } else {
//            $this->log->debug("Index mismatch");
//            return $this->getIndex(true);
//        }
//    }

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
//    private function selectVariant(): array
//    {
//        $index = $this->index->get();
//        $count = 3;
//        foreach ($index["variants"] as $version => $variants) {
//            if (isset($variants[ $this->build_id ])) {
//                $this->log->debug("Found remote build: $version/{$this->build_id}");
//                return $variants[ $this->build_id ];
//            } else if (file_exists($this->cache_dir . "/$version/" . $this->build_id)) {
//                $this->log->debug("Found local build: $version/{$this->build_id}");
//                return ["version" => $version];
//            }
//            if(!$count--) {
//                break;
//            }
//        }
//
//        return [];
//    }

    /**
     *
     * @param string $version
     */
//    public function selectVersion(string $version = "")
//    {
//        if ($version) {
//            $variant = $this->getVariantForVersion($version);
//        } elseif ($variant = $this->selectVariant()) {
//            $version = $variant["version"];
//        } else {
//            $version = $this->getLastVersionName();
//        }
//        $so_path = $this->cache_dir . "/" . $version .  "/" . $this->build_id;
//        if ($variant) {
//            if (!file_exists($so_path)) {
//                $this->log->debug("Download object {$this->so_url_prefix}/{$variant["path"]}?raw=true\n  to $so_path");
//                mkdir(dirname($so_path));
//                $this->download("{$this->so_url_prefix}/{$variant["path"]}?raw=true", $so_path);
//            } else {
//                $this->log->debug("Object $so_path found.");
//            }
//        } else {
//            $this->compile($version, $so_path);
//        }
//        $this->log->debug("Create symlink {$this->link}");
//        @symlink($version .  "/" . $this->build_id, $this->link);
//        if (!file_exists($this->link)) {
//            throw new \RuntimeException("Could not create symlink {$this->link} -> {$so_path}");
//        }
//    }


//    public function getExtPath(bool $autoload = true) : string
//    {
//        if (!file_exists($this->link) && $autoload) {
//            $this->log->debug("Link {$this->link} not found. Resolve problem.");
//            $this->selectVersion();
//        }
//        return $this->link;
//    }

    /**
     * @param string $url
     * @return string
     */
    private function httpGET(string $url): string
    {
        $context = stream_context_create([
            "http" => [
                "user_agent" => "ion-wrapper/0.1",
                "headers" => "Accept: */*"
            ],
        ]);
        return file_get_contents($url, false, $context);
    }

//    private function download(string $url, string $path)
//    {
//        $this->log->progressStart('Downloading');
//        $binary = $this->httpGET($url);
//        $this->log->progressEnd();
//        if ($binary) {
//            file_put_contents($path, $binary);
//        } else {
//            throw new \RuntimeException("Failed to download $url");
//        }
//    }

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

    private function exec(string $command, string $log = ""): int
    {
        if ($log) {
            $this->log->debug("exec: $command\n    Log: $log");
            passthru("($command) 2>&1 1>{$log}", $code);
        } else {
            $this->log->debug("exec: $command");
            passthru("($command) 2>&1", $code);

        }
        return $code;
    }

    private function supervisor(string $command, string $log = ""): int
    {

    }

    /**
     * @param string $name
     * @return array|false|string
     */
    public function bin(string $name) {
        if(isset($this->binaries[$name])) {
            if (file_exists($name)) {
                return $name;
            }
            if ($env = getenv(strtoupper("ion_{$name}_bin"))) {
                return $env;
            }
        }
        return $name;
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
     * @param  Version $version
     * @return string
     */
    public function getPhpCmd(bool $starter = true, Version $version = null): string
    {
        $starter_path = dirname(__DIR__)."/resources/starter.php";
        $version = $version ?: $this->getVersion();
        if (!$version) {
            throw new \RuntimeException("No one version found");
        }
        $flags = "-dextension=".escapeshellarg($version->ext_path);
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

    public function php(string $args, bool $starter = true, Version $version = null): int
    {
        return $this->exec($this->getPhpCmd($starter, $version) . " " . $args);
    }


    public function run(): int
    {
        $router = new Router($this);
        return $router->run($this->argv, Controller::class);
    }
}