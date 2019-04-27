<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Symfony\Component\Yaml\Parser;

abstract class AbstractCommand implements CommandInterface
{
    use ErrorTrait;

    /** @var Initialized[] */
    private $registered_services = [];

    public function register_service($id, $service)
    {
        $this->registered_services[$id] = $service;
    }

    /**
     * @param $args
     * @param $assoc_args
     * @throws Exception
     */
    abstract public function validate($args, $assoc_args);

    /**
     * @param $args
     * @param $assoc_args
     * @return void
     * @throws Exception
     */
    abstract protected function run($args, $assoc_args);

    /**
     * @param $args
     * @param $assoc_args
     * @return array Loaded tasks
     */
    public function load_tasks($args, $assoc_args)
    {
        $tasks = [];

        if (!empty($assoc_args['tasks']) && file_exists($assoc_args['tasks'])) {
            $parser = new Parser();
            $candidates = $parser->parseFile($assoc_args['tasks']);
            if ($candidates) {
                foreach ($candidates as $name => $data) {
                    if (!$args || in_array($name, $args)) {
                        $tasks[$name] = $this->get_task_args($data);
                    }
                }
            }
        }

        if (!$tasks) {
            // single task, defined by CLI arguments
            $tasks = [
                [
                    'args'     => $args,
                    'globals'  => $this->get_global_assoc_args($assoc_args),
                    'services' => $this->get_services_assoc_args($assoc_args),
                ],
            ];
        }

        return $tasks;
    }

    public function __invoke($args, $assoc_args)
    {
        $tasks = $this->load_tasks($args, $assoc_args);

        // validation
        $error = false;
        foreach ($tasks as $name => $task) {
            try {
                // initialize services
                foreach ($this->registered_services as $id => $service) {
                    $service->init_task($task['args'], $task['services'][$id], $task['globals']);
                }
                $this->validate($task['args'], $task['globals']);
            } catch (Exception $e) {
                $this->error(sprintf('task %s: %s', $name, $e->getMessage()));
                $error = true;
            }
        }
        if ($error) {
            exit(1);
        }

        try {
            foreach ($tasks as $name => $task) {
                if (is_string($name)) {
                    echo "$name\n";
                }
                $this->run($task['args'], $task['globals']);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function get_global_assoc_args($assoc_args)
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

    private function get_services_assoc_args($data)
    {
        $services_args = [];
        foreach ($this->registered_services as $id => $service) {
            $services_args[$id] = [];
            $length = strlen($id) + 1;
            foreach ($data as $key => $value) {
                if (strpos($key, $id . '-') === 0) {
                    $services_args[$id][substr($key, $length)] = $value;
                }
            }
        }

        return $services_args;
    }

    private function get_task_args($data)
    {
        // get args
        $args = [];
        if (array_key_exists('fields', $data)) {
            $args = $data['fields'];
            unset($data['fields']);
        }

        return [
            'args'     => $args,
            'globals'  => $this->get_global_assoc_args($data),
            'services' => $this->get_services_assoc_args($data),
        ];
    }
}