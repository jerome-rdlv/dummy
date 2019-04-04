<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface GeneratorInterface
{
    /**
     * @param array|null $options
     * @param integer $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($options, $post_id = null);
}