<?php
/**
 *
 */

namespace Ionizer\Component;


use Ionizer\Log;

class Config implements \ArrayAccess
{
    private $path;

    private $config = [];

    private $changed = false;
    private $log;

    public function __construct(string $path, Log $log)
    {
        $this->config = $this->getDefaultConfig();
        $this->path = $path;
        $this->log = $log;
        if (file_exists($this->path)) {
            $this->log->debug("Config '{$this->path}' found");
            if (!is_readable($this->path)) {
                throw new \RuntimeException("Config file '{$this->path}' not readable (invalid permissions?)");
            }
            $config = file_get_contents($this->path);
            $config = json_decode($config, true);
            if (json_last_error()) {
                $this->log->error("Broken config: ".json_last_error_msg()."\nUse default config");
            } else {
                $this->config = array_merge($config, $this->config);
            }
        } else {
            $this->changed = true;
            $this->log->debug("Create new config {$this->path}");
            $this->flush();
        }
        $this->path = realpath($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getAll(): array
    {
        return $this->config;
    }

    public static function getDefaultConfig(): array
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
        ];
    }

    public function isChanged(): bool
    {
        return $this->changed;
    }

    public function flush()
    {
        if (!$this->changed) {
            return;
        }
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) {
            throw new \RuntimeException("Saving configuration failed: json error: " . json_last_error_msg());
        }
        file_put_contents(
            $this->path,
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }


    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        if (!isset($this->config[$offset])) {
            throw new \RuntimeException("Configuration <$offset> does not exists.");
        }
        return $this->config[$offset];
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if (!isset($this->config[$offset])) {
            throw new \RuntimeException("Configuration <$offset> does not exists.");
        }
        $this->config[$offset] = $value;
        $this->changed = true;
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        if (!isset($this->config[$offset])) {
            throw new \RuntimeException("Configuration <$offset> does not exists.");
        }
        $this->config[$offset] = $this->getDefaultConfig()[$offset];
        $this->changed = true;
    }
}