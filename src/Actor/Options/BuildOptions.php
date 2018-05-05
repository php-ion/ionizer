<?php
/**
 *
 */

namespace Ionizer\Actor\Options;



class BuildOptions implements OptionsInterface
{
    /**
     * @var string run specific PHPUnit test group
     */
//    public $group = "";
    /**
     * @var bool compile the extension
     */
    public $make = false;
    /**
     * @var bool compile with debug extension (require --make option)
     */
    public $debug = false;
    /**
     * @var string do clean build from runtime compile files ('ext' or 'all')
     */
    public $clean = "ext";
    /**
     * @var bool do clean depends from runtime compile files
     */
    public $clean_deps = false;
    /**
     * @var bool prepare source for build. Run load submodules, run phpize, run configure
     */
    public $prepare = false;
    /**
     * @var bool build extension with symbols for code coverage
     */
    public $coverage = false;
    /**
     * @var bool show info about compiled extension
     */
    public $info = false;

    /**
     * @var string
     */
    public $binary = "";
    /**
     * @var bool run PHPUnit tests
     */
//    public $test = "";
    /**
     * @var bool Print SO diag-info
     */
//    public $diag = false;
//    public $noini = false;
//    public $ini = false;
//    public $test_path = "";

//    public $gdb = true;
//    public $binary = "";

    /**
     * Compile ION extension (make required)
     */
    public function setMake(bool $mode = true)
    {
        $this->make = $mode;
    }

    /**
     * Enable debug. Compile with gdb symbols and dev functions.
     */
    public function setDebug(bool $mode = true)
    {
        $this->debug = $mode;
    }

    /**
     * Deletes all the already compiled object files.
     * @param string $mode
     */
    public function setClean(string $mode)
    {
        $this->clean = true;
        if ($mode == "all") {
            $this->clean_deps = true;
        }
    }

    /**
     * Prepare the build environment for a PHP extension (phpize required).
     */
    public function setPrepare(bool $mode = true)
    {
        $this->prepare = $mode;
    }

    /**
     * Compile extension with code coverage and generate code coverage report in Clover XML format.
     */
    public function setCoverage(bool $mode = true)
    {
        $this->coverage = $mode;
    }

    /**
     * Prints info about the extension
     */
    public function setInfoParam()
    {
        $this->info = true;
    }

    /**
     * Prints useful for extension information about this OS.
     */
    public function setSystemParam()
    {
        $this->system = true;
    }

    /**
     * @param string $path
     */
    public function setTestParam(string $path = "")
    {
        $this->test = true;
        $this->test_path = $path;
    }

    /**
     * Only runs tests from the dev group.
     */
    public function setTestDevParam()
    {
        $this->test_group = "dev";
        $this->test = true;
    }

    public function setDiagParam()
    {
        $this->diag = true;
    }

    public function setIniParam()
    {
        $this->ini = true;
    }

    /**
     * Runs tests via GDB (gdb required).
     * Mode "local" means run the program via gdb command.
     * Mode "server" means run the program via gdb-server for remote debug.
     *
     * @param string $mode
     */
    public function setGDBParam(string $mode = "local")
    {
        $this->gdb = $mode;
    }

    /**
     * Copy compiled extension to $path
     * @param string $path
     */
    public function setBinaryParam(string $path)
    {
        $this->binary = $path;
    }

    /**
     * Build for CI systems
     * Alias: --debug --coverage --system --clean=all --prepare --make --info --test
     */
    public function setCIParam()
    {
        $this->setDebugParam();
        $this->setCoverageParam();
        $this->setSystemParam();
        $this->setCleanParam('all');
        $this->setPrepareParam();
        $this->setMakeParam();
        $this->setInfoParam();
        $this->setTestParam();
        $this->setGDBParam();
    }

    public function setIDEParam()
    {
        $this->setDebugParam();
        $this->setSystemParam();
        $this->setCleanParam('all');
        $this->setPrepareParam();
        $this->setMakeParam();
        $this->setInfoParam();
        $this->setTestParam();
    }

    public function setBuildParam()
    {
        $this->setCleanParam("all");
        $this->setPrepareParam();
        $this->setMakeParam();
    }
}