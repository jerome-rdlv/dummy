<?php


namespace Rdlv\WordPress\Dummy;


interface Initialized
{
    /**
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function init($args, $assoc_args);
}