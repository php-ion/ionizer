<?php
/**
 *
 */

namespace Ionizer\Component;


use Ionizer\Ionizer;

class Version
{
    const DRAFT = 1;
    const BRANCH = 2;
    /**
     * @var int
     */
    public $flags = 0;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $version_dir;
    /**
     * @var string
     */
    public $ext_path;
    /**
     * @var string
     */
    public $repo_path;

    /**
     * @var string
     */
    public $binary_url;

    /**
     * @var string
     */
    private $cache_dir;
    /**
     * @var string
     */
    private $build_id;

    public function __construct(Ionizer $ionizer)
    {
        $this->cache_dir = $ionizer->cache_dir;
        $this->build_id  = $ionizer->getBuildID();
    }

    public function setVersionID(string $version, string $binary_url_path = "")
    {
        $this->name      = $version;
        $this->version_dir  = $this->cache_dir . "/" . $version;
        $this->repo_path    = $this->version_dir . "/repo";
        $this->ext_path     = $this->version_dir . "/" . $this->build_id;
        if ($binary_url_path) {
            $this->binary_url = Ionizer::BUILDS_URL . "/" . $binary_url_path . "?raw=true";
        }
    }

    public function setVersionPath(string $path)
    {
        $path = realpath($path);
        $this->version_dir = $path;
        $this->repo_path   = $path;
        $this->ext_path    = $path . "/src/modules/ion.so";
    }

    public function extExists(): bool
    {
        return $this->ext_path && file_exists($this->ext_path);
    }

    public function hasBinary(): bool
    {
        return (bool)$this->binary_url;
    }

    public function __toString()
    {
        return $this->name ?: $this->repo_path ?: "unknown";
    }

    public function isDraft(): bool
    {
        return $this->flags & self::DRAFT;
    }

    public function isBranch(): bool
    {
        return $this->flags & self::DRAFT;
    }


}