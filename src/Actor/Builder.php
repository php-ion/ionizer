<?php

namespace Ionizer\Actor;


use Ionizer\Helper\BaseHelper;
use Ionizer\Helper\LinuxHelper;
use Ionizer\Helper\MacOsHelper;
use Ionizer\Ionizer;
use Ionizer\Actor\Options\BuildOptions;

class Builder {

    const SEGEV_CODE = 139;

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

    /**
     * @var string[]
     */
    const ION_CONFIGURE = ['--with-ion'];
    /**
     * @var string[]
     */
    const EVENT_CONFIGURE = [
        '--disable-libevent-install',
        '--enable-malloc-replacement=yes',
        '--disable-libevent-regress',
    ];

    /**
     * @var BuildOptions
     */
    public $options;
    /**
     * @var Ionizer
     */
    public $ionizer;

    public $gdb_cmd = 'gdb';

    public function __construct(string $path, Ionizer $ionizer, BuildOptions $options) {
        $this->options = $options;
        $this->ionizer = $ionizer;
        $this->path = realpath($path);
        if (!$this->path) {
            throw new \InvalidArgumentException("Invalid build path '$path'");
        }
        foreach($this->binaries as $name => $path) {
            if(getenv(strtoupper("ion_{$name}_exec"))) {
                $this->binaries[$name] = getenv(strtoupper("ion_{$name}_exec"));
            }
        }

        $this->nproc = $this->ionizer->helper->getCPUCount();
        if($this->nproc < 1) {
            $this->nproc = 1;
        }
//        if(!PHP_ZTS) {
//            $this->event_confugure[] = "--disable-thread-support";
//        }
        if(PHP_DEBUG || ZEND_DEBUG_BUILD) {
            $this->options->setDebug();
        }
        $this->gdb_cmd = $this->getBin("gdb")
            . ' -ex "handle SIGHUP nostop SIGCHLD nostop" -ex "run" -ex "thread apply all bt"'
            . ' -ex "set pagination 0" -batch -return-child-result -silent --args';
    }

    /**
     * Get program binary name/path
     * @param string $name short name
     * @param array $env
     *
     * @return string
     */
    public function getBin(string $name, array $env = []) {
        if($env) {
            foreach($env as $var => &$value) {
                $value = $var."=".$value;
            }
            $env = implode(" ", $env)." ";
        } else {
            $env = "";
        }
        if(isset($this->binaries[$name])) {
            return $env.$this->binaries[$name];
        } else {
            return $env.$name;
        }
    }

    /**
     * Write string to stderr
     *
     * @param string $msg
     *
     * @return $this
     */
//    public function write(string $msg) {
//        fwrite(STDERR, $msg);
//        return $this;
//    }

    /**
     * Write line to stderr
     *
     * @param string $msg
     *
     * @return BuildRunner
     */
//    public function line(string $msg) {
//        return $this->write($msg."\n");
//    }

    /**
     * @param string $option
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
//    private function getOptionInfo(string $option, string $key = '', $default = '') {
//        if(isset($this->options[$option])) {
//            if($key) {
//                return $this->options[$option][$key] ?? $default;
//            } else {
//                return $this->options[$option];
//            }
//        } else {
//            throw new LogicException("Option <$option> doesn't exists");
//        }
//    }

    /**
     * @param string $option
     *
     * @return string
     */
//    private function getShort(string $option) {
//        return $this->getOptionInfo($option, 'short', '');
//    }
//
//    private function getDesc(string $option) {
//        return $this->getOptionInfo($option, 'desc', '');
//    }
//
//    private function getExtra(string $option) {
//        return $this->getOptionInfo($option, 'extra', '');
//    }

    /**
     * Checks whether the parameter
     *
     * @param string $long
     *
     * @return bool
     *
     */
//    public function hasOption(string $long) {
//        $short = $this->getShort($long);
//        $options = getopt($short, [$long]);
//        $val = $this->opts[ $long ] ?? $options[ $long ] ?? $options[ $short ] ?? null;
//        if ($val && strtolower($val) === "no") {
//            return false;
//        } else {
//            return isset($val);
//        }
//    }

    /**
     * Sets parameter
     * @param string $long
     * @param string $value
     *
     * @return $this
     */
//    public function setOption(string $long, string $value = "") {
//        $this->opts[$long] = $value;
//        return $this;
//    }

    /**
     * @param string $long
     * @param mixed $default
     *
     * @return mixed
     */
//    public function getOption(string $long, $default = null) {
//        if(isset($this->opts[$long])) {
//            return $this->opts[$long];
//        }
//        $short = $this->getShort($long);
//        $options = getopt($short."::", [$long."::"]);
//        if(isset($options[ $long ])) {
//            return $options[ $long ];
//        } elseif($short && isset($options[ $short ])) {
//            return $options[ $short ];
//        } else {
//            return $default;
//        }
//    }

    /**
     * Run dispatcher
     */
    public function run() {
//        $this->selectIncludes();


//        $php_flags = trim($php_flags);


//        if($this->hasOption("ide")) {
//            $this->setOption('debug');
//
//            $this->setOption('system');
//            $this->setOption('clean-deps');
//            $this->setOption('clean');
//            $this->setOption('prepare');
//            $this->setOption('make');
//            $this->setOption('info');
//
//            $this->setOption('test');
//        }

        $ion_configure   = self::ION_CONFIGURE;
        $event_configure = self::EVENT_CONFIGURE;

        if($this->options->debug) {
            $ion_configure[]   = "--enable-ion-debug";
            $event_configure[] = "--enable-debug-mode=yes";
        }

        if($this->options->coverage) {
            $ion_configure[] = "--enable-ion-coverage";
        }
//        if($this->params->system) {
//            $this->printSystemInfo();
//        }
//
//        if($this->params->diag) {
//            $this->printInfo();
//            return;
//        }

//        if($this->hasOption("help")) {
//            $this->help();
//            return;
//        }

//        if($this->params->ini) {
//            echo file_get_contents("stubs/ION.ini");
//            return;
//        }

//        if($this->hasOption("build") || $this->hasOption("install")) {
//            $this->setOption("prepare");
//            $this->setOption("make");
//            $this->setOption("clean");
//            $this->setOption("clean-deps");
//        }

//        if($this->hasOption("setup")) {
//            $this->setOption("prepare");
//            $this->setOption("make");
//            $this->setOption("clean");
//            $this->setOption("install");
//        }

//        if($this->options->gdb) {
//            $gdb = self::GDB_LOCAL;
//        } elseif($this->options->gdb_server) {
//            $gdb = self::GDB_SERVER;
//        } else {
//            $gdb = self::GDB_NONE;
//        }

        if($this->options->clean_deps) {
            if(file_exists("{$this->path}/src/deps/libevent/Makefile")) {
                $this->exec($this->getBin('make').' clean', "{$this->path}/src/deps/libevent");
                $this->exec("rm -f {$this->path}/src/deps/libevent/Makefile");
            }
            if(file_exists("{$this->path}/src/deps/libevent/configure") && $this->options->prepare) {
                $this->exec("rm -f {$this->path}/src/deps/libevent/configure");
            }
        }
        if($this->options->clean == "ext") {
            if(file_exists("{$this->path}/src/Makefile")) {
                rename("{$this->path}/src/deps/libevent", 'libevent'); // protect libevent from clean
                try {
                    $this->exec($this->getBin('make').' clean', "{$this->path}/src/");
                } finally {
                    rename('libevent', "{$this->path}/src/deps/libevent");
                }
            }
            if(file_exists("{$this->path}/src/configure") && $this->options->prepare) {
                $this->exec($this->getBin('phpize').' --clean', "{$this->path}/src/");
            }
        }

        if($this->options->prepare) {
            if (!file_exists("{$this->path}/src/deps/libevent/.git")) {
                $this->exec('git submodule update --init --recursive', $this->path);
            }
            if (!file_exists("{$this->path}/src/deps/libevent/configure")) {
                $this->exec('autoreconf -i', "{$this->path}/src/deps/libevent");
                $this->exec('./autogen.sh', "{$this->path}/src/deps/libevent");
            }

            if(!file_exists("{$this->path}/src/deps/libevent/Makefile")) {
                $this->configure("{$this->path}/src/deps/libevent", $event_configure);
            }
            $this->exec($this->getBin('make') . ' -j' . $this->ionizer->helper->getCPUCount(), "{$this->path}/src/deps/libevent");

            $this->exec($this->getBin('phpize'), "{$this->path}/src/");
            $this->configure("{$this->path}/src", $ion_configure);
        }

        if($this->options->make) {
            $this->exec($this->getBin('make').' -j'.$this->ionizer->helper->getCPUCount(),  "{$this->path}/src/");
        }

//        if($this->options->info) {
//            $this->exec($this->getBin('php') . ' -e ' . $php_flags . ' -dextension=./src/modules/ion.so '
//                .__FILE__." --diagnostic", false, $gdb);
//        }


        if($this->options->binary) {
            if(file_exists("{$this->path}/src/modules/ion.so")) {
                if (!$this->options->debug) {
                    if ($this->ionizer->helper instanceof MacOsHelper) {
                        $this->exec($this->getBin('strip')." -x {$this->path}/src/modules/ion.so");
                    } elseif ($this->ionizer->helper instanceof LinuxHelper) {
                        $this->exec($this->getBin('strip')." {$this->path}/src/modules/ion.so");
                    }
                }
                copy("src/modules/ion.so", $this->options->binary);
                $this->ionizer->log->info("Extension location: ".realpath($this->options->binary));
            } else {
                throw new \RuntimeException("Failed to copy complied extension from src/modules/ion.so to {$this->options->binary}");
            }
        } else {
            $this->ionizer->log->info("Extension location: {$this->path}/src/modules/ion.so");
        }
    }

    public function printInfo() {
        $info = [];
        $ion = new \ReflectionExtension('ion');
        $info[] = $ion->info();
        foreach($ion->getINIEntries() as $ini => $value) {
            $info[] = "ini $ini = ".var_export($value, true);
        }
        foreach($ion->getConstants() as $constant => $value) {
            $info[] = "const $constant = ".var_export($value, true);
        }
        foreach($ion->getFunctions() as $function) {
            $info[] = $this->_scanFunction($function);
        }
        foreach($ion->getClasses() as $class) {
            $mods = [];
            if($class->isFinal()) {
                $mods[] = "final";
            }
            if($class->isInterface()) {
                $mods[] = "interface";
            } elseif($class->isTrait()) {
                $mods[] = "trait";
            } else {
                if($class->isAbstract()) {
                    $mods[] = "abstract";
                }
                $mods[] = "class";
            }

            $info[] = implode(' ', $mods)." {$class->name} {";
            if($class->getParentClass()) {
                $info[] = "  extends {$class->getParentClass()->name}";
            }
            foreach($class->getInterfaceNames() as $interface) {
                $info[] = "  implements {$interface}";
            }
            foreach($class->getTraitNames() as $trait) {
                $info[] = "  use {$trait}";
            }
            foreach($class->getConstants() as $constant => $value) {
                $info[] = "  const {$class->name}::{$constant} = ".var_export($value, true);
            }
            foreach($class->getProperties() as $prop_name => $prop) {
                /** @var \ReflectionProperty $prop */
                $mods = implode(' ', \Reflection::getModifierNames($prop->getModifiers()));
                if($prop->class !== $class->name) {
                    $info[] = "  prop $mods {$prop->class}::\${$prop->name}";
                } else {
                    $info[] = "  prop $mods \${$prop->name}";
                }

            }
            foreach($class->getMethods() as $method) {
                $info[] = $this->_scanFunction($method, $class->name);
            }

            $info[] = "}";
        }
        echo implode("\n", $info)."\n";
    }

    /**
     * @param \ReflectionFunctionAbstract $function
     * @return string
     */
    private function _scanFunction(\ReflectionFunctionAbstract $function, $class_name = "") {
        $params = [];
        foreach($function->getParameters() as $param) {
            /* @var \ReflectionParameter $param */
            $type = "";
            $param_name = "$".$param->name;
            if($param->getClass()) {
                $type = $param->getClass()->name;
            } elseif ($param->hasType()) {
                $type = $param->getType();
            } elseif ($param->isArray()) {
                $type = "Array";
            } elseif ($param->isCallable()) {
                $type = "Callable";
            }
            if($param->isVariadic()) {
                $param_name = "...".$param_name;
            }
            if($type) {
                $param_name = $type." ".$param_name;
            }
            if($param->isOptional()) {
                $params[] = "[ ".$param_name." ]";
            } else {
                $params[] = $param_name;
            }
        }
        if($function->hasReturnType()) {
            $return = " : ".$function->getReturnType();
        } else {
            $return = "";
        }
        $declare = $function->name;
        if($function instanceof \ReflectionFunction) {
            $declare = "function {$function->name}";
        } elseif ($function instanceof \ReflectionMethod) {
            $mods =  implode(' ', \Reflection::getModifierNames($function->getModifiers()));
            if($function->class !== $class_name) {
                $declare = "  method {$mods} {$function->class}::{$function->name}";
            } else {
                $declare = "  method {$mods} {$function->name}";
            }

        }
        return "{$declare}(".implode(", ", $params).")$return";
    }


    /**
     * @param string $cwd
     * @param array $options
     * @param array $cflags
     * @param array $ldflags
     */
    public function configure(string $cwd, array $options) {
        $flags = 0;
        if ($this->options->debug) {
            $flags |= BaseHelper::BUILD_DEBUG;
        }
        if ($this->options->coverage) {
            $flags |= BaseHelper::BUILD_COVERAGE;
        }
        $cmd = $this->ionizer->helper->buildFlags($flags) . " ./configure " . implode(" ", $options);
        $this->exec($cmd, $cwd);
    }

    /**
     * @param string $cmd
     * @param string|null $cwd
     * @param int $gdb
     */
    public function exec(string $cmd, string $cwd = null) {
        $prev_cwd = null;
        if($cwd) {
            $cwd = realpath($cwd);
            $prev_cwd = getcwd(); // backup cwd
            chdir($cwd);
        }
        $this->ionizer->log->info("*** ".getcwd().": $cmd");
//        if($gdb == self::GDB_LOCAL) {
//            $run_cmd = $this->getBin('gdb').' -ex "handle SIGHUP nostop SIGCHLD nostop" -ex "run" -ex "thread apply all bt" -ex "set pagination 0" -batch -return-child-result -silent --args  '.$cmd;
//            $this->line("*** Using gdb: $run_cmd");
//        } else {
            $run_cmd = $cmd.' 2>&1';
//        }
        passthru($run_cmd, $code);

        if($prev_cwd) { // rollback cwd
            chdir($prev_cwd);
        }
        if($code) {
            throw new \RuntimeException("Command $cmd failed", $code);
        }
    }
}
