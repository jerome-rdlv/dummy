<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

abstract class AbstractCommand implements CommandInterface
{
    use ErrorTrait;

    /** @var Initialized[] */
    private $registered_services = [];

    public function register_service($service)
    {
        $this->registered_services[] = $service;
    }

    /**
     * @param $args
     * @param $assoc_args
     * @throws Exception
     */
    abstract protected function validate($args, $assoc_args);

    /**
     * @param $args
     * @param $assoc_args
     * @return void
     * @throws Exception
     */
    abstract protected function run($args, $assoc_args);

    public function __invoke($args, $assoc_args)
    {
        try {
            $this->validate($args, $assoc_args);
            foreach ($this->registered_services as $service) {
                $service->init($args, $assoc_args);
            }
            $this->run($args, $assoc_args);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}