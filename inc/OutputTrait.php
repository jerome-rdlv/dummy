<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;
use WP_CLI\ExitException;

trait OutputTrait
{
    function error($message)
    {
        echo "\n";
        try {
            WP_CLI::error($message);
        } catch (ExitException $e) {
        }
    }
}