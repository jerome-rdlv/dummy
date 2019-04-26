<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface Initialized
{
    /**
     * @param array $args
     * @param array $assoc_args
     * @param array $globals
     * @return void
     * @throws Exception
     */
    public function init_task($args, $assoc_args, $globals);
}