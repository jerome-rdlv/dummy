<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use WP_CLI;
use WP_CLI\ExitException;

class CommandRunner
{
    /** @var CommandInterface */
    private $command;

    /**
     * @param CommandInterface $command
     */
    public function __construct($command)
    {
        $this->command = $command;
    }

    public function __invoke($args, $assoc_args)
    {
        $cmd = $this->command;
        try {
            return $cmd($args, $assoc_args);
        } catch (Exception $e) {
            return $this->print_error($e);
        }
    }

    /**
     * @param Exception $exception
     * @return int Return code
     */
    protected function print_error($exception)
    {
        if (class_exists('WP_CLI')) {
            try {
                WP_CLI::error($exception->getMessage());
                exit(1);
            } catch (ExitException $e) {
            }
        }
        echo $exception->getMessage();
        return 1;
    }
}