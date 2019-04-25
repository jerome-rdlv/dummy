<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;

trait ErrorTrait
{
    public function error($message)
    {
        try {
            WP_CLI::error($message);
        } catch (WP_CLI\ExitException $e) {
            echo $e->getMessage();
        }
    }
}