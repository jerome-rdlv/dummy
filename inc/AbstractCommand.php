<?php


namespace Rdlv\WordPress\Dummy;


abstract class AbstractCommand implements CommandInterface
{
    use OutputTrait;
    
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
            exit(1);
        }
    }
}