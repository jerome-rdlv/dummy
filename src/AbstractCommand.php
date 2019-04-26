<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Symfony\Component\Yaml\Parser;

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
                foreach ($this->registered_services as $service) {
                    $service->init_task($task[0], $task[1]);
                }
                $this->run($task[0], $task[1]);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            exit(1);
        }
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