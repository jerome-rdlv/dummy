<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use WP_CLI;

trait ErrorTrait
{
    public function error($message)
    {
        try {
            WP_CLI::error($message);
        } catch (Exception $e) {
            echo $message;
        }
    }
}