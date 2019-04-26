<?php


namespace Rdlv\WordPress\Dummy;


interface CommandInterface
{
    /**
     * @param [] $args
     * @param [] $assoc_args
     * @return void
     */
    public function __invoke($args, $assoc_args);

    /**
     * @param string $id
     * @param Initialized $service
     * @return void
     */
    public function register_service($id, $service);
}