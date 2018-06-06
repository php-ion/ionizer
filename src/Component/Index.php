<?php
/**
 *
 */

namespace Ionizer\Component;


use Ionizer\Ionizer;

class Index
{
    private $ionizer;
    private $path;
    private $index;
    public function __construct(string $path, Ionizer $ionizer)
    {
        $this->path = $path;
        $this->ionizer = $ionizer;
        if (!file_exists($path)) {
            $this->ionizer->log->debug("Index mismatch");
            $this->update();
        } else {
            $this->ionizer->log->debug("Read index from cache {$path}");
            $this->index = json_decode(file_get_contents($path), true);
        }
    }

    /**
     * Upload new index-json file from URL Ionizer::INDEX_URL
     */
    public function update()
    {
        $context = stream_context_create([
            "http" => [
                "user_agent" => "ionizer/" . Ionizer::VERSION,
                "headers" => "Accept: */*"
            ]
        ]);
        $this->ionizer->log->debug("Update index from " . Ionizer::INDEX_URL);
        $response = @file_get_contents(Ionizer::INDEX_URL, false, $context);
        if (!$response) {
            throw new \RuntimeException("Can't load index from " . Ionizer::INDEX_URL . ": " . (error_get_last()["message"] ?? "unknown"));
        }
        $data = json_decode($response, true);
        if (json_last_error()) {
            throw new \RuntimeException("Broken json index from " . Ionizer::INDEX_URL . ": " . json_last_error_msg());
        }
        $this->ionizer->log->debug("Store index to cache {$this->path}");
        file_put_contents($this->path, $response);
        $this->index = $data;
    }

    public function getBuildOs(string $os_name, string $os_release, string $os_family) : string
    {
        if (isset($this->index["os"][$os_family])) {
            foreach ($this->index["os"][$os_family] as $mask => $build_os) {
                if(fnmatch($mask, "$os_name-$os_release")) {
                    return $build_os;
                }
            }
        }

        return $os_name . "-" . $os_release;
    }

    /**
     * @return Version|null
     */
    public function getLastPossibleVersion(): ?Version
    {
        $v = new Version($this->ionizer);
        $build_id = $this->ionizer->getBuildID();
        foreach ($this->index["variants"] as $version => $variants) {
            if (isset($variants[ $build_id ])) {
                $this->ionizer->log->debug("Found remote build: $version/{$build_id}");
                $v->setVersionID($version, $variants[ $build_id ]["path"]);
                return $v;
            }
        }
        return null;
    }

    public function hasVersion(string $version): bool
    {
        return isset($this->index["variants"][$version][ $this->ionizer->getBuildID() ]);
    }

    /**
     * Returns version from build index
     *
     * @param string $version
     * @return Version|null
     */
    public function getVersion(string $version): ?Version
    {
        $info = $this->index["variants"][$version][ $this->ionizer->getBuildID() ] ?? null;
        if ($info) {
            $v = new Version($this->ionizer);
            $v->setVersionID($version, $info["path"]);
            return $v;
        } else {
            return null;
        }
    }

    public function getVersions(bool $all = false): array
    {
        $vers = [];
        $build_id = $this->ionizer->getBuildID();
        foreach ($this->index["variants"] as $version => $variants) {
            if ($all || isset($variants[ $build_id ])) {
                $v = new Version($this->ionizer);
                $v->setVersionID($version, $variants[ $build_id ]["path"] ?? "");
                $vers[$version] = $v;
            }
        }
        return $vers;
    }

    /**
     * Returns version info
     *
     * @param string $version
     * @return array|null
     */
    public function searchVersion(string $version): ?array
    {
        if (isset($this->index["variants"][$version])) {
            if (isset($this->index["variants"][$version][ $this->ionizer->getBuildID() ])) {
                return $this->index["variants"][$version][ $this->ionizer->getBuildID() ];
            } else {
                return [];
            }
        } else {
            throw new \RuntimeException("Not found variant for version $version");
        }
    }

    /**
     * Returns actual stable version
     * @return string
     */
    public function getLastVersionName()
    {
        return key($this->index["variants"]) ?: "master";
    }
}