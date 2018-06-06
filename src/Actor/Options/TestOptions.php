<?php

namespace Ionizer\Actor\Options;


class TestOptions implements OptionsInterface
{
    /**
     * @var bool Disable all .ini files
     */
    public $noini = false;

    /**
     * @var string File or directory path which tests cases to run
     */
    public $path;

    /**
     * @var string Filter which tests to run
     */
    public $filter;

    /**
     * @var string Only runs tests from the specified group(s)
     */
    public $group = "";

}