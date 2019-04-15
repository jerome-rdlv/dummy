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
    public function init($args, $assoc_args);
}