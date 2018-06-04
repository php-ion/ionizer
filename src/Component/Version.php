<?php
/**
 *
 */

namespace Ionizer\Component;


class Version
{
    /**
     * @var bool
     */
    public $external = false;
    /**
     * @var string
     */
    public $version;
    /**
     * @var
     */
    public $os;
    /**
     * @var
     */
    public $link;
    public $link_filename;
    /**
     * @var
     */
    public $ext_path;
    /**
     * @var
     */
    public $dist_path;

    public function __construct()
    {

    }

    public function hasBinary(): bool
    {

    }

    public function download()
    {

    }

}