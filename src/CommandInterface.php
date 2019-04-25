<?php


namespace Rdlv\WordPress\Dummy;


interface CommandInterface
{
    public function __invoke($args, $assoc_args);

    /**
     * @param Initialized $service
     * @return void
     */
    public function register_service($service);
}