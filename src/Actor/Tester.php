<?php

namespace Ionizer\Actor;


use Ionizer\Actor\Options\TestOptions;
use Ionizer\Ionizer;

class Tester
{

    /**
     * @var TestOptions
     */
    public $params;
    /**
     * @var Ionizer
     */
    public $ionizer;
    /**
     * @var string
     */
    public $path;

    public function __construct(string $path, Ionizer $ionizer, TestOptions $params)
    {
        $this->ionizer = $ionizer;
        $this->params = $params;
        $this->path = $path;
    }

    public function run()
    {
        if($this->params->group) {
            $group = "--group=".$this->params->group;
        } else {
            $group = "";
        }
        $this->ionizer->php($this->ionizer->bin('phpunit')." --colors=always $group {$this->path}");
        if($this->params->coverage) {
//            $this->ionizer->exec($this->ionizer->bin('lcov')." --directory . --capture --output-file coverage.info");
//            $this->ionizer->exec($this->ionizer->bin('lcov')." --remove coverage.info 'src/deps/*' '*.h' --output-file coverage.info");
//            $this->ionizer->exec($this->ionizer->bin('lcov')." --list coverage.info");
        }
    }
}