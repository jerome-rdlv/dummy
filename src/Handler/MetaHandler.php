<?php


namespace Rdlv\WordPress\Dummy\Handler;

use Rdlv\WordPress\Dummy\HandlerInterface;

/**
 * Populate post meta
 *
 * Example:
 *
 *      {id}:custom_field=raw:your_value
 */
class MetaHandler implements HandlerInterface
{
    public function generate($post_id, $field)
    {
        update_post_meta(
            $post_id,
            $field->name,
            $field->get_value($post_id)
        );
    }
}