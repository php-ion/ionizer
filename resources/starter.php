<?php
/**
 * This is ionizer's starter file.
 */

namespace Ionizer;

function __ionizer_starter()
{
    $debug = (bool)getenv("IONIZER_DEBUG", false);
    if (!extension_loaded("ion")) {
        throw new \Error("Oops. The extension ION not loaded.");
    }
    try {

        if (!$debug) {
            $flags = getenv("IONIZER_FLAGS", false);
            $title = @cli_get_process_title();
            if ($title) {
                // remove noise flags
                $title = str_replace($flags, "", $title);
                @cli_set_process_title($title);
            }
        }
    } catch (\Throwable $e) {
        if ($debug) {
            fwrite(STDERR, $e);
        }
    }
}

@__ionizer_starter();