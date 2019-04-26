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

    /**
     * @param string $doc Documentation to extend
     * @return string
     */
    public function extend_doc($doc);
}