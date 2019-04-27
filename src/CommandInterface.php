<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface CommandInterface
{
    /**
     * @param [] $args
     * @param [] $assoc_args
     * @return integer
     * @throws Exception
     */
    public function __invoke($args, $assoc_args);

    /**
     * @param string $id
     * @param Initialized $service
     * @return void
     */
    public function register_service($id, $service);
}