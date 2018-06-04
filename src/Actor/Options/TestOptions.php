<?php

namespace Ionizer\Actor\Options;


class TestOptions implements OptionsInterface
{
    /**
     * @var string run specific PHPUnit test group
     */
    public $group = "";

    /**
     * @var bool Disable all .ini files
     */
    public $noini = false;

    /**
     * @var string path to ION project directory
     */
    public $ion_path = "";

}