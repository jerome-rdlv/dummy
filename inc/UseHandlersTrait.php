<?php


namespace Rdlv\WordPress\Dummy;


trait UseHandlersTrait
{
    /** @var HandlerInterface[] */
    private $handlers = [];

    public function add_handler($id, HandlerInterface $handler)
    {
        $this->handlers[$id] = $handler;
    }

    public function get_handler($id)
    {
        if (array_key_exists($id, $this->handlers)) {
            return $this->handlers[$id];
        }
        return null;
    }
    
    public function get_handlers()
    {
        return $this->handlers;
    }
}