<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class Field
{
    /** @var string Alias used on CLI to target the field */
    public $alias;

    /** @var string Full field identification, with handler and name */
    public $key;

    /** @var HandlerInterface $handler */
    public $handler = null;

    /**
     * @var string Field name
     * Can be a posts table field name, a meta_name or an ACF field name.
     */
    public $name;

    /**
     * @var GeneratorCall $callback Field value
     */
    public $callback;

    public function generate($post_id)
    {
        if ($this->handler) {
            $this->handler->generate($post_id, $this);
        }
    }

    /**
     * @param integer|null $post_id
     * @return mixed
     * @throws Exception
     */
    public function get_value($post_id = null)
    {
        return $this->callback->get($post_id);
    }
}