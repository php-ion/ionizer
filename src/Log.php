<?php

namespace Ionizer;


class Log
{
    public $level = LOG_NOTICE;

    public static $levels = [
        LOG_DEBUG => "debug",
        LOG_INFO => "info",
        LOG_NOTICE => "notice",
        LOG_WARNING => "warning",
        LOG_ERR => "error",
    ];

    public function __construct(int $level)
    {
        $this->level = $level;
    }

    public function log($message, $level)
    {
        if ($this->level >= $level) {
            if ($level == LOG_INFO) {
                $prefix = "[ION] ";
            } else {
                $prefix = "[ION.".strtoupper(self::$levels[$level])."] ";
            }

            if ($message instanceof \Throwable) {
                $errors = [];
                $e = $message;
                do {
                    $errors[] = get_class($e) . ": " . $e->getMessage() . " on "
                        . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString()  . "\n";
                } while($e = $e->getPrevious());
                $message = implode("\n", $errors);
            }
            fwrite(STDERR, $prefix . str_replace("\n", "\n{$prefix}", $message) . "\n");
        }
    }

    public function debug($message)
    {
        $this->log($message, LOG_DEBUG);
    }

    public function info($message)
    {
        $this->log($message, LOG_INFO);
    }

    public function notice($message)
    {
        $this->log($message, LOG_INFO);
    }

    public function warning($message)
    {
        $this->log($message, LOG_WARNING);
    }

    public function error($message)
    {
        $this->log($message, LOG_ERR);
    }

    public function progressStart(string $label)
    {

    }

    public function progressEnd(string $end = "OK")
    {

    }

    public function isDebug() : bool
    {
        return $this->level == LOG_DEBUG;
    }
}