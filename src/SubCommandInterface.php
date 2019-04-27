<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface SubCommandInterface
{
    /**
     * @param $args
     * @param $assoc_args
     * @throws Exception
     */
    public function validate($args, $assoc_args);

    /**
     * @param $args
     * @param $assoc_args
     * @return void
     * @throws Exception
     */
    public function run($args, $assoc_args);
}