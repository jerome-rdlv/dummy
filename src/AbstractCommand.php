<?php

namespace Rdlv\WordPress\Dummy;


abstract class AbstractCommand implements CommandInterface
{
    /** @var Initialized[] */
    protected $registered_services = [];

    public function register_service($id, $service)
    {
        $this->registered_services[$id] = $service;
    }

    public function get_global_assoc_args($assoc_args)
    {
        // detect services keys
        $keys_regex = '/^(' . implode('|', array_filter(
                array_keys($this->registered_services),
                function ($key) {
                    return is_numeric($key) ? false : $key;
                }
            )) . ')-/';

        $globals = [];
        foreach ($assoc_args as $key => $value) {
            if (!preg_match($keys_regex, $key)) {
                $globals[$key] = $value;
            }
        }

        return $globals;
    }

    public function get_services_assoc_args($assoc_args)
    {
        $services_args = [];
        foreach ($this->registered_services as $id => $service) {
            $services_args[$id] = [];
            $length = strlen($id) + 1;
            foreach ($assoc_args as $key => $value) {
                if (strpos($key, $id . '-') === 0) {
                    $services_args[$id][substr($key, $length)] = $value;
                }
            }
        }

        return $services_args;
    }
}