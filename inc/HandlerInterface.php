<?php


namespace Rdlv\WordPress\Dummy;


interface HandlerInterface
{
    /**
     * @param integer $post_id
     * @param Field $field
     * @return void
     */
    public function generate($post_id, $field);
}