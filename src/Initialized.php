<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface Initialized
{
    /**
     * @param array $args
     * @param array $assoc_args
     * @return void
     * @throws Exception
     */
    public function init_task($args, $assoc_args);
}