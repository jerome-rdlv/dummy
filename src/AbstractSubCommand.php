<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;

abstract class AbstractSubCommand extends AbstractCommand implements SubCommandInterface
{
    public function __invoke($args, $assoc_args)
    {
        // initialize services
        $globals_assoc_args = $this->get_global_assoc_args($assoc_args);
        $services_assoc_args = $this->get_services_assoc_args($assoc_args);
        foreach ($this->registered_services as $id => $service) {
            $service->init_task($args, $services_assoc_args[$id], $globals_assoc_args);
        }
        $this->validate($args, $globals_assoc_args);
        $this->run($args, $globals_assoc_args);
        return 0;
    }

    protected function print_progress($message, $count = 0, $total = 0)
    {
        $end = $count === $total;
        if (!$total) {
            $detail = '';
        } else {
            $detail = $end ? '(' . $total . ')' : '(' . $count . '/' . $total . ')';
        }
        printf(
            "\r %s " . $message . " %s",
            WP_CLI::colorize($end ? '%G✔%n' : '%w·%n'),
            str_pad($detail, (int)(floor(log($total) + 1) * 2 + 3), ' ')
        );
        if ($end) {
            echo "\n";
        }
    }
}