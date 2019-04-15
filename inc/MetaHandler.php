<?php


namespace Rdlv\WordPress\Dummy;

/**
 * Populate post meta
 * 
 * Example:
 * 
 *      meta:custom_field=html
 */
class MetaHandler implements HandlerInterface, Initialized
{
    private $post_type = null;

    public function init($args, $assoc_args)
    {
        if (!empty($assoc_args['post-type'])) {
            $this->post_type = $assoc_args['post-type'];
        }
    }

    public function generate($post_id, $field)
    {
        update_post_meta(
            $post_id,
            $field->name,
            $field->get_value($post_id)
        );
    }
}