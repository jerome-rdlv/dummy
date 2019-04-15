<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;

abstract class AbstractCommand implements CommandInterface
{
    use ErrorTrait;
    
    /** @var Initialized[] */
    private $registered_services = [];
    
    public function register_service($service)
    {
        $this->registered_services[] = $service;
    }
    
    abstract protected function run();
    
    protected function init($args, $assoc_args)
    {
        foreach ($this->registered_services as $service) {
            $service->init($args, $assoc_args);
        }

        try {
            $this->run();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}