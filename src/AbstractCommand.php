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
            $tasks = [[$args, $assoc_args]];
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
                $this->validate($task[0], $task[1]);
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
                $global_args = $this->get_global_args($task[1]);
                foreach ($this->registered_services as $id => $service) {
                    $service->init_task(
                        $task[0],
                        $this->get_service_args($task[1], $id),
                        $global_args
                    );
                }
                $this->run($task[0], $global_args);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function get_global_args($assoc_args)
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

    private function get_service_args($assoc_args, $id)
    {
        $unprefixed = [];
        $length = strlen($id) + 1;
        foreach ($assoc_args as $key => $value) {
            if (strpos($key, $id . '-') === 0) {
                $unprefixed[substr($key, $length)] = $value;
            }
        }

        return $unprefixed;
    }

    private function get_task_args($data)
    {
        $fields = [];
        if (array_key_exists('fields', $data)) {
            $fields = $data['fields'];
            unset($data['fields']);
        }
        return [$fields, $data];
    }
}