<?php


namespace Rdlv\WordPress\Dummy;


interface TypeInterface
{
    /**
     * @param $value
     * @return mixed
     */
    public function get($post_id, $options);
}