<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface HandlerInterface
{
    /**
     * @param integer $post_id
     * @param Field $field
     * @return void
     * @throws Exception
     */
    public function generate($post_id, $field);
}