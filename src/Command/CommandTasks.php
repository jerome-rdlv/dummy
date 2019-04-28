<?php

namespace Rdlv\WordPress\Dummy\Command;

use Exception;
use Rdlv\WordPress\Dummy\AbstractCommand;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\MetaCommandInterface;
use Rdlv\WordPress\Dummy\Task;
use Symfony\Component\Yaml\Parser;

/**
 * Run predefined tasks
 *
 * This command read task definitions in a file and execute
 * one or more tasks depending on provided arguments, for example
 *
 *      wp dummy tasks references
 *
 * will execute task 'references'. If no arguments provided,
 * all tasks are executed in order.
 *
 * ## TASKS
 *
 * [--file=<file>]
 * : Tasks file
 * ---
 * default: dummy.yml
 * ---
 *
 * [<tasks>...]
 * : Tasks to execute
 */
class CommandTasks extends AbstractCommand implements MetaCommandInterface
{
    private $commands = [];

    public function add_command($id, $command)
    {
        $this->commands[$id] = $command;
    }

    /**
     * @param $args
     * @param $assoc_args
     * @return Task[] Loaded tasks
     * @throws DummyException
     */
    public function load_tasks($args, $assoc_args)
    {
        $selected = [];

        if (empty($assoc_args['file'])) {
            throw new DummyException("'file' argument can not be empty.");
        }

        $file = $assoc_args['file'];
        $parser = new Parser();
        $tasks = $parser->parseFile($file);

        if (!$tasks) {
            throw new DummyException(sprintf("no tasks found in tasks file '%s'", $file));
        }

        $errors = [];
        foreach ($tasks as $name => $data) {
            if (!array_key_exists('command', $data)) {
                $errors[] = sprintf("task '%s' has no command entry.", $name);
            } elseif (!array_key_exists($data['command'], $this->commands)) {
                $errors[] = sprintf("task '%s' refers to inexistent command '%s'.", $name, $data['command']);
            }
        }
        if ($errors) {
            throw new DummyException($errors);
        }

        if ($args) {
            $inexistents = [];
            foreach ($args as $name) {
                if (!array_key_exists($name, $tasks)) {
                    $inexistents[] = $name;
                }
            }
            if ($inexistents) {
                throw new DummyException(sprintf(
                    "following tasks are not found: %s",
                    implode(', ', $inexistents)
                ));
            }

            // keep args order when args given
            foreach ($args as $name) {
                $selected[$name] = $this->get_task($tasks[$name]);
            }
        } else {
            foreach ($tasks as $name => $data) {
                $selected[$name] = $this->get_task($data);
            }
        }

        return $selected;
    }

    private function get_task($data)
    {
        // get args
        $args = [];
        if (array_key_exists('fields', $data)) {
            $args = $data['fields'];
            unset($data['fields']);
        }

        $command = $this->commands[$data['command']];
        unset($data['command']);

        return new Task(
            $command,
            $args,
            $this->get_global_assoc_args($data),
            $this->get_services_assoc_args($data)
        );
    }

    public function __invoke($args, $assoc_args)
    {
        $tasks = $this->load_tasks($args, $assoc_args);

        // validation
        $errors = [];
        try {
            foreach ($tasks as $name => $task) {
                // initialize services
                foreach ($this->registered_services as $id => $service) {
                    $service->init_task(
                        $task->get_args(),
                        $task->get_service_args($id),
                        $task->get_globals()
                    );
                }
                $task->validate();
            }
        } catch (Exception $e) {
            $errors[] = sprintf('task %s: %s', $name, $e->getMessage());
        }
        if ($errors) {
            throw new DummyException(implode("\n", $errors));
        }

        foreach ($tasks as $name => $task) {
            if (is_string($name)) {
                echo "task $name:\n";
            }
            $task->run();
        }

        return 0;
    }
}