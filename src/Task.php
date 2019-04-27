<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class Task
{
    private $command;
    private $args;
    private $global_assoc_args;
    private $services_assoc_args;

    /**
     * @param SubCommandInterface $command
     * @param array $args
     * @param array $globals
     * @param array $services
     */
    public function __construct($command, $args, $globals, $services)
    {
        $this->command = $command;
        $this->args = $args;
        $this->global_assoc_args = $globals;
        $this->services_assoc_args = $services;
    }

    public function get_args()
    {
        return $this->args;
    }

    public function get_service_args($id)
    {
        return array_key_exists($id, $this->services_assoc_args) ? $this->services_assoc_args[$id] : [];
    }

    public function get_globals()
    {
        return $this->global_assoc_args;
    }

    public function get_global($key)
    {
        return array_key_exists($key, $this->global_assoc_args) ? $this->global_assoc_args[$key] : null;
    }

    /**
     * @throws Exception
     */
    public function validate()
    {
        $this->command->validate($this->args, $this->global_assoc_args);
    }

    /**
     * @throws Exception
     */
    public function run()
    {
        $this->command->run($this->args, $this->global_assoc_args);
    }

}