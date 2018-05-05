<?php
/**
 *
 */

namespace Ionizer\Actor\Options;



class ServerOptions implements OptionsInterface
{

    /**
     * @var string IP and port, by default 127.0.0.1:8088
     */
    public $listen = "127.0.0.1:8000";

    /**
     * @var int count of worker processes
     */
    public $workers = 2;
}