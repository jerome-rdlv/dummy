<?php


namespace Rdlv\WordPress\Dummy;


interface UseHandlersInterface
{
    /**
     * @param string $id
     * @param HandlerInterface $handler
     * @return void
     */
    public function add_handler($id, HandlerInterface $handler);

    /**
     * @param string $id
     * @return HandlerInterface
     */
    public function get_handler($id);

    /**
     * @return HandlerInterface[]
     */
    public function get_handlers();
}