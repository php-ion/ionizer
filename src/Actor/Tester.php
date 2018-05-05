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
    public function __construct(Ionizer $ionizer, TestOptions $params)
    {
        $this->ionizer = $ionizer;
        $this->params = $params;
    }

    public function run()
    {
        $php_flags = "";
        if($this->params->noini) {
            $php_flags .= "-n ";
        }

        if($this->params->test) {
            if($this->params->test_group) {
                $group = "--group=".$this->params->test_group;
            } else {
                $group = "";
            }
            $phpunit = $this->getBin('php')." -e {$php_flags} -dextension=./src/modules/ion.so ".$this->getBin('phpunit')." --colors=always $group ".$this->params->test_path;
            $this->exec($phpunit, false, $gdb);
            if($this->params->coverage) {
                $this->exec($this->getBin('lcov')." --directory . --capture --output-file coverage.info");
                $this->exec($this->getBin('lcov')." --remove coverage.info 'src/deps/*' '*.h' --output-file coverage.info");
                $this->exec($this->getBin('lcov')." --list coverage.info");
            }
        }
    }
}